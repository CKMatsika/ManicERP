<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\Hash;

/**
 * Minimal RFC 6238 TOTP implementation used for privileged staff MFA.
 */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function provisioningUri(string $secret, string $accountName, string $issuer = 'ManicERP'): string
    {
        $label = rawurlencode($issuer.':'.$accountName);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            $secret,
            rawurlencode($issuer),
        );
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $key = $this->base32Decode($secret);
        $binary = pack('J', $counter);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % 1_000_000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = time();

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $now + ($offset * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        return $codes;
    }

    /** @param array<int, string> $codes */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make($code), $codes);
    }

    /** @param array<int, string> $hashed */
    public function useRecoveryCode(string $code, array $hashed): ?int
    {
        foreach ($hashed as $index => $hash) {
            if (Hash::check(strtoupper($code), $hash)) {
                return $index;
            }
        }

        return null;
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        $output = '';

        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $output;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');
        $bits = '';

        foreach (str_split($encoded) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $output .= chr(bindec($chunk));
            }
        }

        return $output;
    }
}
