<?php

require_once __DIR__ . '/../vendor/autoload.php';

function ensureRegistrationInvoiceSchema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $columns = [
        "ALTER TABLE event_registrations ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER organization",
        "ALTER TABLE event_registrations ADD COLUMN address TEXT DEFAULT NULL AFTER country",
        "ALTER TABLE event_registrations ADD COLUMN attendee_count INT NOT NULL DEFAULT 1 AFTER address",
        "ALTER TABLE event_registrations ADD COLUMN attendee_details LONGTEXT DEFAULT NULL AFTER attendee_count",
        "ALTER TABLE event_registrations ADD COLUMN invoice_number VARCHAR(50) DEFAULT NULL AFTER attendee_details",
        "ALTER TABLE event_registrations ADD COLUMN invoice_path VARCHAR(255) DEFAULT NULL AFTER invoice_number",
        "ALTER TABLE event_registrations ADD COLUMN event_id INT DEFAULT NULL AFTER event_name",
        "ALTER TABLE event_registrations ADD COLUMN currency_code VARCHAR(10) DEFAULT 'USD' AFTER event_id",
        "ALTER TABLE event_registrations ADD COLUMN unit_price_amount DECIMAL(10,2) DEFAULT 0.00 AFTER currency_code",
        "ALTER TABLE event_registrations ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0.00 AFTER unit_price_amount",
    ];

    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore duplicate-column errors so this can run safely on every request.
        }
    }
}

function parseEventPrice(string $priceText): array
{
    $priceText = trim($priceText);
    $currency = 'USD';
    $amount = 0.0;

    // Case-SENSITIVE, and bounded by non-letters. The old pattern was
    // /([A-Z]{3})/i, which matched the first three letters of any word — so
    // the current price text "From USD 599 Per Delegate" yielded "FRO", and
    // every invoice since has printed "UNIT PRICE (FRO)" and "FRO 599.00".
    // A real currency code is written in capitals, so requiring capitals both
    // fixes this and still finds USD, KES, ZAR, EUR, GBP, AED and the rest.
    // The lookarounds let it also match a code written tight against the
    // amount, e.g. "USD599".
    if (preg_match('/(?<![A-Za-z])([A-Z]{3})(?![A-Za-z])/', $priceText, $currencyMatch)) {
        $currency = $currencyMatch[1];
    }

    if (preg_match('/(\d[\d,]*(?:\.\d{1,2})?)/', $priceText, $amountMatch)) {
        $amount = (float) str_replace(',', '', $amountMatch[1]);
    }

    return [$currency, $amount];
}

function generateInvoiceNumber(int $registrationId): string
{
    return 'INV-' . date('Ymd') . '-' . str_pad((string) $registrationId, 4, '0', STR_PAD_LEFT);
}

function buildInvoicePayload(array $registration, array $eventRecord): array
{
    $attendees = json_decode($registration['attendee_details'] ?? '[]', true);
    if (!is_array($attendees)) {
        $attendees = [];
    }

    return [
        'invoice_number' => $registration['invoice_number'],
        'invoice_date' => date('d F Y'),
        'due_date' => date('d F Y', strtotime('+15 days')),
        'terms' => 'Net 15',
        'bill_to' => [
            'company' => $registration['organization'] ?: ($registration['first_name'] . ' ' . $registration['last_name']),
            'contact_person' => trim($registration['first_name'] . ' ' . $registration['last_name']),
            'address' => $registration['address'] ?: $registration['country'],
            'email' => $registration['email'],
        ],
        'event' => [
            'name' => $eventRecord['title'],
            'date' => $eventRecord['date_display'],
            'location' => $eventRecord['location'],
        ],
        'attendee_count' => (int) $registration['attendee_count'],
        'currency_code' => $registration['currency_code'] ?: 'USD',
        'unit_price_amount' => (float) $registration['unit_price_amount'],
        'total_amount' => (float) $registration['total_amount'],
        'attendees' => $attendees,
    ];
}

function generateInvoicePdf(array $invoicePayload, string $outputPath): array
{
    try {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);

        $accent = [0, 177, 64];
        $text   = [17, 24, 39];
        $muted  = [107, 114, 128];
        $light  = [229, 231, 235];

        $pageWidth = $pdf->GetPageWidth();
        $rightEdge = $pageWidth - 15;

        // Logo + accent rule
        $logoPath = __DIR__ . '/../assets/documents/prosperminds logo.png';
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 15, 12, 38);
        }
        $pdf->SetDrawColor(...$accent);
        $pdf->SetLineWidth(0.7);
        $pdf->Line(15, 28, $rightEdge, 28);

        // Title
        $pdf->SetXY($rightEdge - 60, 10);
        $pdf->SetTextColor(...$text);
        $pdf->SetFont('Helvetica', 'B', 20);
        $pdf->Cell(60, 10, 'INVOICE', 0, 2, 'R');
        $pdf->SetX($rightEdge - 60);
        $pdf->SetTextColor(...$muted);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(60, 5, 'www.prosper-minds.com', 0, 2, 'R');

        $topY = 36;

        // FROM block
        $pdf->SetXY(15, $topY);
        $pdf->SetTextColor(...$accent);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(90, 5, 'FROM', 0, 2);
        $pdf->SetTextColor(...$text);
        $pdf->SetFont('Helvetica', '', 10);
        $fromLines = [
            'Prosperminds',
            'Twiga Towers 7th Floor Moi Avenue',
            'P.O. Box',
            'Nairobi, Kenya',
            'info@prosper-minds.com',
            '+254 740 582302 / +254 741 174909',
            'KRA PIN: P052360042N',
        ];
        $pdf->SetX(15);
        foreach ($fromLines as $line) {
            $pdf->SetX(15);
            $pdf->Cell(90, 5.5, $line, 0, 2);
        }

        // Invoice info block
        $infoX = $rightEdge - 75;
        $pdf->SetXY($infoX, $topY);
        $labels = [
            ['Invoice No.', (string) $invoicePayload['invoice_number']],
            ['Invoice Date', (string) $invoicePayload['invoice_date']],
            ['Due Date', (string) $invoicePayload['due_date']],
            ['Terms', (string) $invoicePayload['terms']],
        ];
        foreach ($labels as [$label, $value]) {
            $pdf->SetX($infoX);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(...$text);
            $pdf->Cell(28, 6, $label, 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(47, 6, $value, 0, 2);
        }

        // BILL TO
        $billY = $topY + 45;
        $pdf->SetXY(15, $billY);
        $pdf->SetTextColor(...$accent);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(120, 5, 'BILL TO', 0, 2);
        $pdf->SetTextColor(...$text);
        $pdf->SetFont('Helvetica', '', 10);
        $bill = $invoicePayload['bill_to'];
        foreach ([$bill['company'], $bill['contact_person'], $bill['address'], $bill['email']] as $line) {
            $pdf->SetX(15);
            $pdf->MultiCell(120, 5.5, (string) ($line ?: ''), 0, 'L');
        }

        // Items table header
        $tableY = $billY + 38;
        $currency = $invoicePayload['currency_code'] ?: 'USD';
        $pdf->SetXY(15, $tableY);
        $pdf->SetFillColor(...$accent);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 9);
        $tableWidth = $rightEdge - 15;
        $pdf->Cell($tableWidth * 0.46, 8, ' DESCRIPTION', 0, 0, 'L', true);
        $pdf->Cell($tableWidth * 0.14, 8, 'QTY', 0, 0, 'C', true);
        $pdf->Cell($tableWidth * 0.20, 8, "UNIT PRICE ($currency)", 0, 0, 'C', true);
        $pdf->Cell($tableWidth * 0.20, 8, "AMOUNT ($currency)", 0, 1, 'C', true);

        $description = $invoicePayload['event']['name'] . ' - Delegate Fee ('
            . $invoicePayload['event']['date'] . ' | ' . $invoicePayload['event']['location'] . ')';
        $attendeeNames = trim(implode(', ', array_map(
            static fn ($a) => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
            $invoicePayload['attendees'] ?? []
        )), ', ');

        $rowY = $tableY + 8;
        $pdf->SetXY(15, $rowY);
        $pdf->SetTextColor(...$text);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell($tableWidth * 0.46, 5, $description, 0, 'L');
        $descEndY = $pdf->GetY();

        if ($attendeeNames !== '') {
            $pdf->SetX(15);
            $pdf->SetTextColor(...$muted);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->MultiCell($tableWidth * 0.46, 4.5, 'Delegates: ' . $attendeeNames, 0, 'L');
            $descEndY = max($descEndY, $pdf->GetY());
        }

        $pdf->SetTextColor(...$text);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(15 + $tableWidth * 0.46, $rowY);
        $pdf->Cell($tableWidth * 0.14, 5, (string) (int) $invoicePayload['attendee_count'], 0, 0, 'C');
        $pdf->Cell($tableWidth * 0.20, 5, number_format((float) $invoicePayload['unit_price_amount'], 2), 0, 0, 'C');
        $pdf->Cell($tableWidth * 0.20, 5, number_format((float) $invoicePayload['total_amount'], 2), 0, 1, 'C');

        $afterRowY = max($descEndY, $rowY + 5) + 4;
        $pdf->SetDrawColor(...$light);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $afterRowY, $rightEdge, $afterRowY);

        // Totals
        $summaryY = $afterRowY + 6;
        $pdf->SetXY(15, $summaryY);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$text);
        $pdf->Cell($tableWidth - 45, 6, 'Subtotal', 0, 0, 'R');
        $pdf->Cell(45, 6, number_format((float) $invoicePayload['total_amount'], 2), 0, 1, 'R');
        $pdf->SetX(15);
        $pdf->Cell($tableWidth - 45, 6, 'Tax / VAT (0%)', 0, 0, 'R');
        $pdf->Cell(45, 6, '0.00', 0, 1, 'R');

        $totalDueY = $summaryY + 15;
        $pdf->SetXY($rightEdge - 75, $totalDueY);
        $pdf->SetFillColor(...$accent);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(38, 9, ' TOTAL DUE', 0, 0, 'L', true);
        $pdf->Cell(37, 9, $currency . ' ' . number_format((float) $invoicePayload['total_amount'], 2) . ' ', 0, 1, 'R', true);

        // Payment details
        $paymentY = $totalDueY + 16;
        $pdf->SetXY(15, $paymentY);
        $pdf->SetTextColor(...$accent);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(100, 5, 'PAYMENT DETAILS', 0, 2);
        $paymentRows = [
            ['Bank Name', 'Kenya Commercial Bank Limited'],
            ['Account Name', 'Prosperminds Limited'],
            ['Account Number', '1353427463'],
            ['SWIFT / BIC', 'Available on request'],
            ['Branch', 'Garden City'],
        ];
        foreach ($paymentRows as [$label, $value]) {
            $pdf->SetX(15);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(...$text);
            $pdf->Cell(35, 5.5, $label, 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(100, 5.5, $value, 0, 2);
        }

        // Notes
        $notesY = $paymentY + 50;
        $pdf->SetXY(15, $notesY);
        $pdf->SetTextColor(...$accent);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($tableWidth, 5, 'NOTES', 0, 2);
        $pdf->SetX(15);
        $pdf->SetTextColor(...$text);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell(
            $tableWidth,
            4.5,
            'Thank you for registering with Prosperminds. Please make payment by the due date shown above. '
                . 'For invoice queries, contact info@prosper-minds.com.',
            0,
            'L'
        );

        // Footer
        $pdf->SetY(-18);
        $pdf->SetTextColor(...$muted);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(
            0,
            5,
            'Prosperminds | info@prosper-minds.com | +254 740 582302 / +254 741 174909 | www.prosper-minds.com',
            0,
            0,
            'C'
        );

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $pdf->Output('F', $outputPath);

        if (!is_file($outputPath)) {
            return ['success' => false, 'output' => 'PDF file was not written to disk.'];
        }

        return ['success' => true, 'output' => 'Invoice generated.'];
    } catch (Throwable $e) {
        return ['success' => false, 'output' => 'Invoice generation error: ' . $e->getMessage()];
    }
}

/**
 * Secret for signing invoice links. Read from the environment so it is not in
 * the repository; falls back to a value derived from the database password so
 * an install that has not set one still gets links that cannot be guessed from
 * outside, rather than links signed with a constant.
 */
function pmInvoiceSigningKey(): string
{
    $key = (string) (getenv('INVOICE_LINK_SECRET') ?: '');

    if ($key !== '') {
        return $key;
    }

    return hash('sha256', 'pm-invoice-link|' . (defined('DB_PASS') ? DB_PASS : '') . '|' . (defined('DB_NAME') ? DB_NAME : ''));
}

function pmInvoiceSignature(int $registrationId, int $expires): string
{
    return hash_hmac('sha256', $registrationId . '|' . $expires, pmInvoiceSigningKey());
}

function pmInvoiceSignatureValid(int $registrationId, int $expires, string $signature): bool
{
    // hash_equals, not ===, so a wrong signature cannot be narrowed down by
    // timing how long the comparison takes.
    return hash_equals(pmInvoiceSignature($registrationId, $expires), $signature);
}

/** A link that works for $days and then stops. */
function pmInvoiceLink(int $registrationId, int $days = 30, bool $absolute = true): string
{
    $expires = time() + $days * 86400;
    $query   = 'r=' . $registrationId . '&e=' . $expires . '&s=' . pmInvoiceSignature($registrationId, $expires);
    $path    = '/invoice.php?' . $query;

    if (!$absolute) {
        return $path;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'prosper-minds.com';
    $sch  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    return $sch . '://' . $host . $path;
}
