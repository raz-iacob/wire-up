async (seed) => {
    const encoder = new TextEncoder();

    const toBase64Url = (bytes) =>
        btoa(String.fromCharCode(...new Uint8Array(bytes)))
            .replace(/\+/g, "-")
            .replace(/\//g, "_")
            .replace(/=+$/, "");

    const fromBase64Url = (value) => {
        const base64 = value.replace(/-/g, "+").replace(/_/g, "/");

        return Uint8Array.from(
            atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, "=")),
            (character) => character.charCodeAt(0),
        );
    };

    const concat = (...parts) => {
        const out = new Uint8Array(parts.reduce((total, part) => total + part.length, 0));
        let offset = 0;

        for (const part of parts) {
            out.set(part, offset);
            offset += part.length;
        }

        return out;
    };

    const head = (major, length) =>
        new Uint8Array(
            length < 24
                ? [(major << 5) | length]
                : length < 256
                  ? [(major << 5) | 24, length]
                  : [(major << 5) | 25, length >> 8, length & 255],
        );

    const cbor = {
        int: (value) => (value >= 0 ? head(0, value) : head(1, -1 - value)),
        bytes: (value) => concat(head(2, value.length), value),
        text: (value) => {
            const bytes = encoder.encode(value);

            return concat(head(3, bytes.length), bytes);
        },
        map: (entries) => concat(head(5, entries.length), ...entries.flat()),
    };

    const sha256 = async (data) => new Uint8Array(await crypto.subtle.digest("SHA-256", data));

    const derSignature = (raw) => {
        const integer = (bytes) => {
            let start = 0;

            while (start < bytes.length - 1 && bytes[start] === 0) {
                start++;
            }

            let trimmed = bytes.slice(start);

            if (trimmed[0] & 0x80) {
                trimmed = concat(new Uint8Array([0]), trimmed);
            }

            return concat(new Uint8Array([2, trimmed.length]), trimmed);
        };

        const r = integer(raw.slice(0, 32));
        const s = integer(raw.slice(32));

        return concat(new Uint8Array([0x30, r.length + s.length]), r, s);
    };

    const algorithm = { name: "ECDSA", namedCurve: "P-256" };
    let privateKey;
    let publicKey;
    let credentialId;
    let userHandle;

    if (seed) {
        privateKey = await crypto.subtle.importKey("jwk", seed.jwk, algorithm, true, ["sign"]);
        credentialId = fromBase64Url(seed.credentialId);
        userHandle = seed.userHandle;
    } else {
        ({ privateKey, publicKey } = await crypto.subtle.generateKey(algorithm, true, ["sign", "verify"]));
        credentialId = crypto.getRandomValues(new Uint8Array(32));
    }

    const clientData = (type, challenge) =>
        encoder.encode(
            JSON.stringify({ type, challenge: toBase64Url(challenge), origin: location.origin, crossOrigin: false }),
        );

    const credential = (response) => ({
        id: toBase64Url(credentialId),
        rawId: credentialId.slice().buffer,
        type: "public-key",
        authenticatorAttachment: "platform",
        getClientExtensionResults: () => ({}),
        response,
    });

    navigator.credentials.create = async ({ publicKey: options }) => {
        userHandle = toBase64Url(options.user.id);

        const raw = new Uint8Array(await crypto.subtle.exportKey("raw", publicKey));
        const coseKey = cbor.map([
            [cbor.int(1), cbor.int(2)],
            [cbor.int(3), cbor.int(-7)],
            [cbor.int(-1), cbor.int(1)],
            [cbor.int(-2), cbor.bytes(raw.slice(1, 33))],
            [cbor.int(-3), cbor.bytes(raw.slice(33))],
        ]);

        const authData = concat(
            await sha256(encoder.encode(options.rp.id)),
            new Uint8Array([0x45, 0, 0, 0, 0]),
            new Uint8Array(16),
            new Uint8Array([0, credentialId.length]),
            credentialId,
            coseKey,
        );

        const attestationObject = cbor.map([
            [cbor.text("fmt"), cbor.text("none")],
            [cbor.text("attStmt"), cbor.map([])],
            [cbor.text("authData"), cbor.bytes(authData)],
        ]);

        return credential({
            clientDataJSON: clientData("webauthn.create", options.challenge).slice().buffer,
            attestationObject: attestationObject.slice().buffer,
            getTransports: () => ["internal"],
        });
    };

    const assertion = async (options) => {
        const authData = concat(await sha256(encoder.encode(options.rpId)), new Uint8Array([0x05, 0, 0, 0, 0]));
        const data = clientData("webauthn.get", options.challenge);
        const signature = new Uint8Array(
            await crypto.subtle.sign({ name: "ECDSA", hash: "SHA-256" }, privateKey, concat(authData, await sha256(data))),
        );

        return credential({
            clientDataJSON: data.slice().buffer,
            authenticatorData: authData.slice().buffer,
            signature: derSignature(signature).slice().buffer,
            userHandle: fromBase64Url(userHandle).slice().buffer,
        });
    };

    let pickFromAutofill = null;

    navigator.credentials.get = ({ publicKey: options, mediation, signal }) => {
        if (mediation !== "conditional") {
            return assertion(options);
        }

        return new Promise((resolve, reject) => {
            const pick = () => assertion(options).then(resolve);
            pickFromAutofill = pick;

            signal?.addEventListener("abort", () => {
                if (pickFromAutofill === pick) {
                    pickFromAutofill = null;
                }

                reject(new DOMException("Aborted", "AbortError"));
            });
        });
    };

    window.PublicKeyCredential.isConditionalMediationAvailable = async () => true;

    window.fakePasskey = {
        waitingForAutofill: () => pickFromAutofill !== null,
        pickFromAutofill: () => pickFromAutofill?.(),
    };

    return true;
}
