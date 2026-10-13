<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

/**
 * The file types a document may hold, and how each one is presented.
 */
final class FileTypes
{
    /** Extension => [mime, kind]. */
    private const TYPES = [
        'pdf'  => ['application/pdf', 'pdf'],
        'doc'  => ['application/msword', 'word'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'word'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'word'],
        'rtf'  => ['application/rtf', 'word'],
        'xls'  => ['application/vnd.ms-excel', 'excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'excel'],
        'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'excel'],
        'csv'  => ['text/csv', 'excel'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'powerpoint'],
        'odp'  => ['application/vnd.oasis.opendocument.presentation', 'powerpoint'],
        'txt'  => ['text/plain', 'text'],
        'zip'  => ['application/zip', 'archive'],
        'jpg'  => ['image/jpeg', 'image'],
        'jpeg' => ['image/jpeg', 'image'],
        'png'  => ['image/png', 'image'],
        'webp' => ['image/webp', 'image'],
    ];

    /** File kinds a browser can show by itself, so they get a "View" button. */
    private const VIEWABLE = ['pdf', 'image', 'text'];

    /** @return array<string, string> extension => mime */
    public static function allowedMimes(): array
    {
        $mimes = array_map(static fn (array $type): string => $type[0], self::TYPES);
        /** @var array<string, string> */
        return apply_filters('crnd_allowed_mimes', $mimes);
    }

    public static function isAllowedMime(string $mime): bool
    {
        return in_array($mime, self::allowedMimes(), true);
    }

    public static function kind(string $extension): string
    {
        return self::TYPES[strtolower($extension)][1] ?? 'other';
    }

    public static function isViewable(string $extension): bool
    {
        return in_array(self::kind($extension), self::VIEWABLE, true);
    }

    public static function formatSize(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '';
        }
        return (string) size_format($bytes, $bytes < MB_IN_BYTES ? 0 : 1);
    }
}
