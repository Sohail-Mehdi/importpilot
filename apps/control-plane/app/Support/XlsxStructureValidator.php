<?php

namespace App\Support;

class XlsxStructureValidator
{
    public const MAX_UNCOMPRESSED_BYTES = 209715200;

    public static function validateFile(string $path): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return 'Malformed ZIP/XLSX file.';
        }

        $hasTypes = $zip->locateName('[Content_Types].xml') !== false;
        $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
        if (!$hasTypes || !$hasWorkbook) {
            $zip->close();
            return 'Malformed XLSX file: Missing structural components.';
        }

        $uncompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $uncompressed += $stat['size'] ?? 0;
            if ($uncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                return 'Malformed XLSX file: uncompressed size exceeds limit.';
            }
        }

        $zip->close();
        return null;
    }
}
