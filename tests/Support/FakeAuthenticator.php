<?php

declare(strict_types=1);

namespace Tests\Support;

use OpenSSLAsymmetricKey;
use RuntimeException;

final class FakeAuthenticator
{
    private const int USER_PRESENT = 0x01;

    private const int USER_VERIFIED = 0x04;

    private const int ATTESTED_CREDENTIAL_DATA = 0x40;

    private readonly OpenSSLAsymmetricKey $key;

    private readonly string $credentialId;

    private string $userHandle = '';

    public function __construct(private readonly string $aaguid = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0")
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        throw_if($key === false, RuntimeException::class, 'Could not create a P-256 key.');

        $this->key = $key;
        $this->credentialId = random_bytes(32);
    }

    public static function encode(string $bytes): string
    {
        return mb_rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @return array<string, mixed>
     */
    public function register(array $options, ?string $origin = null): array
    {
        /** @var array{challenge: string, rp: array{id: string}, user: array{id: string}} $options */
        $this->userHandle = $options['user']['id'];

        $authData = hash('sha256', $options['rp']['id'], true)
            .chr(self::USER_PRESENT | self::USER_VERIFIED | self::ATTESTED_CREDENTIAL_DATA)
            .pack('N', 0)
            .$this->aaguid
            .pack('n', mb_strlen($this->credentialId, '8bit'))
            .$this->credentialId
            .$this->publicKey();

        $attestationObject = $this->map([
            [$this->text('fmt'), $this->text('none')],
            [$this->text('attStmt'), $this->map([])],
            [$this->text('authData'), $this->bytes($authData)],
        ]);

        return $this->credential([
            'clientDataJSON' => self::encode($this->clientData('webauthn.create', $options['challenge'], $origin)),
            'attestationObject' => self::encode($attestationObject),
            'transports' => ['internal'],
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @return array<string, mixed>
     */
    public function assert(array $options, ?string $origin = null): array
    {
        /** @var array{challenge: string, rpId: string} $options */
        $authData = hash('sha256', $options['rpId'], true)
            .chr(self::USER_PRESENT | self::USER_VERIFIED)
            .pack('N', 0);

        $clientData = $this->clientData('webauthn.get', $options['challenge'], $origin);

        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $this->credential([
            'clientDataJSON' => self::encode($clientData),
            'authenticatorData' => self::encode($authData),
            'signature' => self::encode((string) $signature),
            'userHandle' => $this->userHandle,
        ]);
    }

    /**
     * @return array{jwk: array<string, string>, credentialId: string, userHandle: string}
     */
    public function export(): array
    {
        /** @var array{ec: array{x: string, y: string, d: string}} $details */
        $details = openssl_pkey_get_details($this->key);

        $part = fn (string $value): string => self::encode(mb_str_pad($value, 32, "\0", STR_PAD_LEFT, '8bit'));

        return [
            'jwk' => [
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => $part($details['ec']['x']),
                'y' => $part($details['ec']['y']),
                'd' => $part($details['ec']['d']),
            ],
            'credentialId' => self::encode($this->credentialId),
            'userHandle' => $this->userHandle,
        ];
    }

    /**
     * @param  list<array{string, string}>  $entries
     */
    private function map(array $entries): string
    {
        return $this->head(5, count($entries)).implode('', array_map(fn (array $entry): string => $entry[0].$entry[1], $entries));
    }

    private function integer(int $value): string
    {
        return $value >= 0 ? $this->head(0, $value) : $this->head(1, -1 - $value);
    }

    private function bytes(string $value): string
    {
        return $this->head(2, mb_strlen($value, '8bit')).$value;
    }

    private function text(string $value): string
    {
        return $this->head(3, mb_strlen($value, '8bit')).$value;
    }

    private function head(int $major, int $length): string
    {
        return match (true) {
            $length < 24 => chr($major << 5 | $length),
            $length < 256 => chr($major << 5 | 24).chr($length),
            default => chr($major << 5 | 25).pack('n', $length),
        };
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function credential(array $response): array
    {
        return [
            'id' => self::encode($this->credentialId),
            'rawId' => self::encode($this->credentialId),
            'type' => 'public-key',
            'authenticatorAttachment' => 'platform',
            'clientExtensionResults' => [],
            'response' => $response,
        ];
    }

    private function clientData(string $type, string $challenge, ?string $origin): string
    {
        /** @var list<string> $origins */
        $origins = config('passkeys.allowed_origins');

        return (string) json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $origin ?? $origins[0],
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
    }

    private function publicKey(): string
    {
        /** @var array{ec: array{x: string, y: string}} $details */
        $details = openssl_pkey_get_details($this->key);

        return $this->map([
            [$this->integer(1), $this->integer(2)],
            [$this->integer(3), $this->integer(-7)],
            [$this->integer(-1), $this->integer(1)],
            [$this->integer(-2), $this->bytes(mb_str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT, '8bit'))],
            [$this->integer(-3), $this->bytes(mb_str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT, '8bit'))],
        ]);
    }
}
