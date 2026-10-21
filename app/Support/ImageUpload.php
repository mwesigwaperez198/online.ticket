<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Validation rules and helpers for organizer uploaded artwork.
 *
 * Only real raster images are accepted. The allow list is checked against the
 * MIME type sniffed from the file content, never against the extension or the
 * client supplied content type, so a renamed script or a PHP polyglot is
 * rejected before it is written to disk.
 */
final class ImageUpload
{
    /**
     * The largest banner the platform accepts, in kilobytes.
     */
    public const MAX_KILOBYTES = 2048;

    /**
     * @var array<int, string>
     */
    public const MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * The file extension that belongs to each allowed MIME type.
     *
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:'.implode(',', self::MIME_TYPES),
            'max:'.self::MAX_KILOBYTES,
            'dimensions:max_width=6000,max_height=6000',
        ];
    }

    /**
     * The extension a stored file must carry for the given MIME type.
     */
    public static function extensionFor(UploadedFile $file): ?string
    {
        $mime = (string) $file->getMimeType();

        return self::EXTENSIONS[$mime] ?? null;
    }
}
