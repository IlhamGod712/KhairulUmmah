<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;

class MimeTerpercaya
{
    public static function of(UploadedFile $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getRealPath());

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    /**
     * @return array<int, string>
     */
    public static function allowedForDocuments(): array
    {
        return ['application/pdf', 'image/jpeg', 'image/png'];
    }

    public static function isAllowedForDocuments(string $mime): bool
    {
        return in_array($mime, self::allowedForDocuments(), true);
    }
}
