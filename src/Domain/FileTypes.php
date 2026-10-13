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

    /**
     * What the content of each kind of file really is, as reported by finfo. WordPress
     * only inspects images, so without this a renamed executable passes as a ".pdf".
     */
    private const CONTENT_MIMES = [
        'pdf'        => ['application/pdf', 'application/x-pdf'],
        // Office formats: the modern ones are ZIP containers, the old ones OLE containers.
        'word'       => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.oasis.opendocument.text', 'application/rtf', 'text/rtf', 'application/zip', 'application/x-zip-compressed', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'excel'      => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.oasis.opendocument.spreadsheet', 'application/zip', 'application/x-zip-compressed', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'text/csv', 'text/plain', 'application/csv'],
        'powerpoint' => ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.oasis.opendocument.presentation', 'application/zip', 'application/x-zip-compressed', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'archive'    => ['application/zip', 'application/x-zip-compressed'],
        'image'      => ['image/jpeg', 'image/png', 'image/webp'],
        'text'       => ['text/plain'],
    ];

    /**
     * Whether the file's actual content matches its extension. Returns true when the
     * server has no fileinfo extension, so uploads keep working there (WordPress' own
     * checks still apply).
     */
    public static function contentMatches(string $path, string $extension): bool
    {
        if (!is_readable($path)) {
            return false;
        }
        if (!class_exists(\finfo::class)) {
            return true;
        }
        $real = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $kind = self::kind($extension);
        /** @var array<string, list<string>> $allowed */
        $allowed = apply_filters('crnd_content_mimes', self::CONTENT_MIMES);
        return in_array($real, $allowed[$kind] ?? [], true);
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
