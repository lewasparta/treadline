<?php
/**
 * TREADLINE - Spreadsheet import reader
 *
 * Reads an uploaded CSV or XLSX file into a plain array of rows (each row is
 * an array of string cell values), so the rest of the importer code never
 * has to care which format the person actually uploaded.
 *
 * Handles the common real-world headaches:
 *  - Excel on non-US locales exports CSV with a semicolon (;) delimiter, not
 *    a comma - the delimiter is auto-detected per file instead of assumed.
 *  - Excel prefixes CSV exports with a UTF-8 byte-order-mark (BOM) - stripped.
 *  - Tab-delimited exports are also detected.
 *  - Native .xlsx files are read directly via ZipArchive + the raw XML parts
 *    (no PhpSpreadsheet/Composer dependency required).
 */

class TL_SpreadsheetError extends Exception {}

/**
 * @return array<int, array<int, string>> Rows of string cell values (first row is the header).
 */
function tl_read_spreadsheet_rows(string $tmpPath, string $originalName): array {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Sniff by content too, in case the browser/OS gave a misleading extension.
    $head = @file_get_contents($tmpPath, false, null, 0, 8) ?: '';
    $isZip = substr($head, 0, 2) === 'PK';

    if ($ext === 'xlsx' || $ext === 'xls' || $isZip) {
        return tl_read_xlsx_rows($tmpPath);
    }
    return tl_read_csv_rows($tmpPath);
}

/** ---------------------------------------------------------------
 *  CSV reading with delimiter auto-detection + BOM stripping
 *  --------------------------------------------------------------- */
function tl_read_csv_rows(string $path): array {
    $content = file_get_contents($path);
    if ($content === false) throw new TL_SpreadsheetError('Could not read the uploaded file.');

    // Strip UTF-8 BOM that Excel/Windows commonly prepends.
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    // Normalise line endings (old Mac \r-only, Windows \r\n, Unix \n).
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    // Detect delimiter from the first non-empty line: comma, semicolon, or tab -
    // whichever appears most often wins. This is what actually fixes files
    // exported by Excel in locales that use ';' as the list separator.
    $lines = explode("\n", $content);
    $sampleLine = '';
    foreach ($lines as $line) {
        if (trim($line) !== '') { $sampleLine = $line; break; }
    }
    $candidates = [',' => 0, ';' => 0, "\t" => 0];
    foreach (array_keys($candidates) as $d) {
        $candidates[$d] = substr_count($sampleLine, $d);
    }
    arsort($candidates);
    $delimiter = array_key_first($candidates);
    if ($candidates[$delimiter] === 0) $delimiter = ','; // no separators found at all - single-column file

    $rows = [];
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if (count($row) === 1 && ($row[0] === null || trim((string)$row[0]) === '')) continue;
        $rows[] = array_map(fn($v) => trim((string)$v), $row);
    }
    fclose($handle);

    if (!$rows) throw new TL_SpreadsheetError('The file appears to be empty.');
    return $rows;
}

/** ---------------------------------------------------------------
 *  Minimal XLSX reading via ZipArchive (no Composer dependency)
 *  --------------------------------------------------------------- */
function tl_read_xlsx_rows(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new TL_SpreadsheetError('This server does not have the PHP "zip" extension enabled, which is required to read .xlsx files. Please save the file as CSV instead, or ask your host to enable php-zip.');
    }
    if (!class_exists('DOMDocument')) {
        throw new TL_SpreadsheetError('This server does not have the PHP "xml"/"dom" extension enabled, which is required to read .xlsx files. Please save the file as CSV instead, or ask your host to enable php-xml.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new TL_SpreadsheetError('Could not open the uploaded file as an Excel workbook.');
    }

    // Shared strings table (most text cells reference an index into this).
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sharedStrings = tl_xlsx_parse_shared_strings($sharedXml);
    }

    // Find the first worksheet. Try the conventional path first, then fall
    // back to whatever sheet the workbook's relationships point at.
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();

    if ($sheetXml === false) {
        throw new TL_SpreadsheetError('Could not find a worksheet inside the Excel file.');
    }

    return tl_xlsx_parse_sheet($sheetXml, $sharedStrings);
}

function tl_xlsx_parse_shared_strings(string $xml): array {
    $doc = new DOMDocument();
    $doc->loadXML($xml, LIBXML_NOCDATA);
    $strings = [];
    foreach ($doc->getElementsByTagName('si') as $si) {
        // A shared string can be a simple <t> or multiple <r><t> rich-text runs - concatenate all text nodes.
        $text = '';
        foreach ($si->getElementsByTagName('t') as $t) {
            $text .= $t->nodeValue;
        }
        $strings[] = $text;
    }
    return $strings;
}

function tl_xlsx_col_to_index(string $colLetters): int {
    $index = 0;
    foreach (str_split($colLetters) as $ch) {
        $index = $index * 26 + (ord($ch) - ord('A') + 1);
    }
    return $index - 1; // zero-based
}

function tl_xlsx_parse_sheet(string $xml, array $sharedStrings): array {
    $doc = new DOMDocument();
    $doc->loadXML($xml, LIBXML_NOCDATA);
    $rows = [];

    foreach ($doc->getElementsByTagName('row') as $rowEl) {
        $rowData = [];
        $maxIndex = -1;
        foreach ($rowEl->getElementsByTagName('c') as $cellEl) {
            $ref = $cellEl->getAttribute('r'); // e.g. "C4"
            preg_match('/^([A-Z]+)/', $ref, $m);
            $colIndex = $m ? tl_xlsx_col_to_index($m[1]) : (count($rowData));
            $type = $cellEl->getAttribute('t');

            $value = '';
            if ($type === 'inlineStr') {
                $isNode = $cellEl->getElementsByTagName('is')->item(0);
                if ($isNode) {
                    foreach ($isNode->getElementsByTagName('t') as $t) $value .= $t->nodeValue;
                }
            } else {
                $vNode = $cellEl->getElementsByTagName('v')->item(0);
                $raw = $vNode ? $vNode->nodeValue : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int)$raw] ?? '';
                } elseif ($type === 'str' || $type === 'b') {
                    $value = $raw;
                } else {
                    // Numeric (including Excel serial dates - left as the raw number;
                    // callers treat all values as text anyway).
                    $value = $raw;
                }
            }
            $rowData[$colIndex] = $value;
            $maxIndex = max($maxIndex, $colIndex);
        }
        // Fill gaps for skipped/empty cells so column positions line up with the header row.
        $ordered = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $ordered[] = trim((string)($rowData[$i] ?? ''));
        }
        if ($ordered && implode('', $ordered) !== '') {
            $rows[] = $ordered;
        }
    }

    if (!$rows) throw new TL_SpreadsheetError('The worksheet appears to be empty.');
    return $rows;
}
