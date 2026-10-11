<?php

namespace App\Services;

use Throwable;

/** Verifies the RSA-SHA256 request proof published by WOPI discovery. */
class WorkspaceWopiProofKeyVerifier
{
    private const WINDOWS_EPOCH_OFFSET_SECONDS = 11644473600;
    private const TICKS_PER_SECOND = 10000000;
    private const DEFAULT_MAX_AGE_SECONDS = 1200;
    private const MAX_FUTURE_SKEW_SECONDS = 60;

    /**
     * @param array<string, mixed> $keys Discovery attributes: modulus, exponent, oldmodulus, oldexponent.
     */
    public function verify(
        mixed $accessToken,
        mixed $url,
        mixed $timestamp,
        mixed $proof,
        mixed $proofOld,
        mixed $keys,
        ?int $nowTimestamp = null,
        int $maxAgeSeconds = self::DEFAULT_MAX_AGE_SECONDS,
    ): bool {
        try {
            if (! is_string($accessToken) || $accessToken === ''
                || ! is_string($url) || $url === ''
                || ! is_array($keys)
                || $maxAgeSeconds < 1 || $maxAgeSeconds > 86400) {
                return false;
            }

            $timestamp = $this->parseUnsignedInteger($timestamp);
            if ($timestamp === null) {
                return false;
            }

            $nowTimestamp ??= $this->windowsFileTimeNow();
            $maxAgeTicks = $maxAgeSeconds * self::TICKS_PER_SECOND;
            $maxFutureSkewTicks = self::MAX_FUTURE_SKEW_SECONDS * self::TICKS_PER_SECOND;
            if (($timestamp > $nowTimestamp && $timestamp - $nowTimestamp > $maxFutureSkewTicks)
                || ($nowTimestamp > $timestamp && $nowTimestamp - $timestamp > $maxAgeTicks)) {
                return false;
            }

            $tokenBytes = $accessToken;
            $urlBytes = strtoupper($url);
            $timestampBytes = pack('J', $timestamp);
            $message = pack('N', strlen($tokenBytes)).$tokenBytes
                .pack('N', strlen($urlBytes)).$urlBytes
                .pack('N', strlen($timestampBytes)).$timestampBytes;

            $currentKey = $this->publicKey($keys['modulus'] ?? null, $keys['exponent'] ?? null);
            $oldKey = $this->publicKey($keys['oldmodulus'] ?? null, $keys['oldexponent'] ?? null);

            return $this->signatureMatches($message, $proof, $currentKey)
                || $this->signatureMatches($message, $proofOld, $currentKey)
                || $this->signatureMatches($message, $proof, $oldKey);
        } catch (Throwable) {
            return false;
        }
    }

    private function parseUnsignedInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (! is_string($value) || $value === '' || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return is_int($parsed) ? $parsed : null;
    }

    private function windowsFileTimeNow(): int
    {
        return (time() + self::WINDOWS_EPOCH_OFFSET_SECONDS) * self::TICKS_PER_SECOND;
    }

    private function signatureMatches(string $message, mixed $encodedSignature, mixed $key): bool
    {
        if (! is_string($encodedSignature) || $encodedSignature === '' || ! $key instanceof \OpenSSLAsymmetricKey) {
            return false;
        }

        $signature = base64_decode($encodedSignature, true);
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return @openssl_verify($message, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function publicKey(mixed $encodedModulus, mixed $encodedExponent): ?\OpenSSLAsymmetricKey
    {
        if (! is_string($encodedModulus) || ! is_string($encodedExponent)) {
            return null;
        }

        $modulus = base64_decode($encodedModulus, true);
        $exponent = base64_decode($encodedExponent, true);
        if (! is_string($modulus) || ! is_string($exponent)
            || strlen($modulus) < 64 || strlen($modulus) > 1024
            || strlen($exponent) < 1 || strlen($exponent) > 8) {
            return null;
        }

        $rsaPublicKey = $this->derSequence($this->derInteger($modulus).$this->derInteger($exponent));
        $pem = "-----BEGIN RSA PUBLIC KEY-----\n"
            .chunk_split(base64_encode($rsaPublicKey), 64, "\n")
            .'-----END RSA PUBLIC KEY-----';
        $key = @openssl_pkey_get_public($pem);
        if (! $key instanceof \OpenSSLAsymmetricKey) {
            return null;
        }

        $details = openssl_pkey_get_details($key);
        if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            return null;
        }

        return $key;
    }

    private function derInteger(string $number): string
    {
        $number = ltrim($number, "\0");
        if ($number === '') {
            $number = "\0";
        }
        if ((ord($number[0]) & 0x80) !== 0) {
            $number = "\0".$number;
        }

        return "\x02".$this->derLength(strlen($number)).$number;
    }

    private function derSequence(string $contents): string
    {
        return "\x30".$this->derLength(strlen($contents)).$contents;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }
}
