<?php
/**
 * Streams an array of rows as a downloadable CSV and ends the request.
 * Safe to call mid-page (after header.php has already echoed the page
 * shell) because header.php wraps everything in ob_start() -- nothing has
 * actually reached the browser yet, so the buffered HTML is simply
 * discarded here before the real CSV headers go out.
 */
function export_csv_and_exit(string $filename, array $headers, iterable $rows): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders ₹/names correctly instead of guessing ANSI
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
