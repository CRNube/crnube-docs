<?php
declare(strict_types=1);

namespace CRNube\Docs\Support;

/**
 * Reads a readable title and a year out of an uploaded file's name, so dropping
 * "Informe-Anual-de-Riesgos-2024.pdf" creates "Informe Anual de Riesgos 2024", filed in 2024.
 */
final class Filename
{
    public static function title(string $filename): string
    {
        $name  = pathinfo($filename, PATHINFO_FILENAME);
        $name  = (string) preg_replace('/[_\-.]+/u', ' ', $name);
        $name  = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            return $filename;
        }
        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }

    /**
     * The last plausible year in the name (1990 to next year), or '' if there is none.
     * The last one wins: in "Ley-9147-Gaceta-19-JULIO-2013" it is 2013.
     */
    public static function year(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        if (!preg_match_all('/(?<!\d)((?:19|20)\d{2})(?!\d)/', $name, $matches)) {
            return '';
        }
        $max = (int) gmdate('Y') + 1;
        foreach (array_reverse($matches[1]) as $year) {
            if ((int) $year >= 1990 && (int) $year <= $max) {
                return $year;
            }
        }
        return '';
    }
}
