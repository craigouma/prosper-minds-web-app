<?php
/**
 * CSV downloads for the admin panel.
 *
 * Every cell here can hold text a stranger typed into a public form, and a
 * spreadsheet treats a cell that starts with = + - or @ as a formula. A name of
 * =HYPERLINK("http://...") would then run when somebody opens the export, so
 * those cells are written with a leading apostrophe, which Excel and Sheets
 * show as plain text and hide.
 */

function pmCsvCell(mixed $value): string
{
    $text = (string) ($value ?? '');

    if ($text !== '' && strpbrk($text[0], "=+-@\t\r") !== false) {
        return "'" . $text;
    }

    return $text;
}

/**
 * Send a CSV and stop. Must be called before any output.
 *
 * @param array<int, string> $headers
 * @param iterable<array<int, mixed>> $rows
 */
function pmCsvSend(string $baseName, array $headers, iterable $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '_' . date('Ymd_His') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    // A byte order mark, so Excel reads the file as UTF-8 and keeps accents.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, array_map('pmCsvCell', $row));
    }

    fclose($out);
    exit;
}
