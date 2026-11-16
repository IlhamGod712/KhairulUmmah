<?php

declare(strict_types=1);

namespace App\Support;

final class AuditSanitizer
{
    /**
     * @param  array<array-key, mixed>|null  $values
     * @return array<array-key, mixed>|null
     */
    public static function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $safeValues = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                continue;
            }

            $safeValues[$key] = is_array($value) ? self::sanitize($value) : $value;
        }

        return $safeValues;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalisedKey = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        foreach ([
            'password',
            'token',
            'cookie',
            'session',
            'csrf',
            'credential',
            'document',
            'dokumen',
            'file',
            'bukti',
            'content',
            'konten',
            'path',
            'isi',
            'email',
            'nohp',
            'nomorhp',
            'nik',
            'nisn',
            'alamat',
            'tanggallahir',
        ] as $sensitivePart) {
            if (str_contains($normalisedKey, $sensitivePart)) {
                return true;
            }
        }

        return $normalisedKey === 'nis';
    }
}
