const toBuffer = (value) => {
    const base64 = value.replace(/-/g, "+").replace(/_/g, "/");
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, "=");

    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0))
        .buffer;
};

const toBase64Url = (buffer) => {
    let binary = "";

    new Uint8Array(buffer).forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary)
        .replace(/\+/g, "-")
        .replace(/\//g, "_")
        .replace(/=+$/, "");
};

const descriptors = (list = []) =>
    list.map((descriptor) => ({ ...descriptor, id: toBuffer(descriptor.id) }));

const wasCancelled = (error) =>
    error?.name === "NotAllowedError" || error?.name === "AbortError";

const credentialJson = (credential, response) => ({
    id: credential.id,
    rawId: toBase64Url(credential.rawId),
    type: credential.type,
    authenticatorAttachment: credential.authenticatorAttachment ?? null,
    clientExtensionResults: credential.getClientExtensionResults?.() ?? {},
    response,
});

export const passkeysSupported = () =>
    typeof window.PublicKeyCredential === "function" &&
    typeof navigator.credentials?.create === "function";

export const passkeyAutofillAvailable = async () =>
    passkeysSupported() &&
    (await window.PublicKeyCredential.isConditionalMediationAvailable?.()) ===
        true;

export async function createPasskey(options) {
    let credential;

    try {
        credential = await navigator.credentials.create({
            publicKey: {
                ...options,
                challenge: toBuffer(options.challenge),
                user: { ...options.user, id: toBuffer(options.user.id) },
                excludeCredentials: descriptors(options.excludeCredentials),
            },
        });
    } catch (error) {
        if (wasCancelled(error)) {
            return null;
        }

        throw error;
    }

    return credentialJson(credential, {
        clientDataJSON: toBase64Url(credential.response.clientDataJSON),
        attestationObject: toBase64Url(credential.response.attestationObject),
        transports: credential.response.getTransports?.() ?? [],
    });
}

export async function getPasskey(options, { mediation, signal } = {}) {
    let credential;

    try {
        credential = await navigator.credentials.get({
            mediation,
            signal,
            publicKey: {
                ...options,
                challenge: toBuffer(options.challenge),
                allowCredentials: descriptors(options.allowCredentials),
            },
        });
    } catch (error) {
        if (wasCancelled(error)) {
            return null;
        }

        throw error;
    }

    if (!credential) {
        return null;
    }

    const { response } = credential;

    return credentialJson(credential, {
        clientDataJSON: toBase64Url(response.clientDataJSON),
        authenticatorData: toBase64Url(response.authenticatorData),
        signature: toBase64Url(response.signature),
        userHandle: response.userHandle
            ? toBase64Url(response.userHandle)
            : null,
    });
}

document.addEventListener("alpine:init", () => {
    // Sign-in page: a "Sign in with a passkey" button plus browser autofill.
    // Autofill (conditional mediation) waits in the background until the visitor
    // picks a passkey from the email field's suggestions; the button aborts it
    // first, because a page can only run one WebAuthn request at a time. Both
    // share one challenge until an attempt uses it, so two requests never race
    // to store different challenges in the session.
    Alpine.data("passkeySignIn", (messages) => ({
        supported: passkeysSupported(),
        busy: false,
        error: "",
        controller: null,
        pendingOptions: null,

        init() {
            this.listen();
        },

        destroy() {
            this.controller?.abort();
        },

        options() {
            this.pendingOptions ??= this.$wire.passkeyOptions();

            return this.pendingOptions;
        },

        async attempt(credential) {
            this.pendingOptions = null;

            await this.$wire.signInWithPasskey(credential);
        },

        async listen() {
            this.controller?.abort();
            const controller = new AbortController();
            this.controller = controller;

            try {
                if (
                    !(await passkeyAutofillAvailable()) ||
                    controller.signal.aborted
                ) {
                    return;
                }

                const options = await this.options();

                if (controller.signal.aborted) {
                    return;
                }

                const credential = await getPasskey(options, {
                    mediation: "conditional",
                    signal: controller.signal,
                });

                if (credential) {
                    await this.attempt(credential);
                }
            } catch {
                // Autofill is a convenience; the button still works.
            }
        },

        async signIn() {
            if (this.busy) {
                return;
            }

            this.controller?.abort();
            this.busy = true;
            this.error = "";

            try {
                const credential = await getPasskey(await this.options());

                if (credential) {
                    await this.attempt(credential);
                } else {
                    this.listen();
                }
            } catch {
                this.pendingOptions = null;
                this.error = messages.failed;
            } finally {
                this.busy = false;
            }
        },
    }));

    // Account screens: password check on the server, then the browser's prompt.
    Alpine.data("passkeyRegistration", (messages) => ({
        supported: passkeysSupported(),
        busy: false,
        error: "",

        async add() {
            if (this.busy) {
                return;
            }

            this.busy = true;
            this.error = "";

            try {
                const options = await this.$wire.registrationOptions();

                if (!options) {
                    return;
                }

                const credential = await createPasskey(options);

                if (credential) {
                    await this.$wire.add(credential);
                }
            } catch (error) {
                this.error =
                    error?.name === "InvalidStateError"
                        ? messages.duplicate
                        : messages.failed;
            } finally {
                this.busy = false;
            }
        },
    }));
});
