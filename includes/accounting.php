<?php

function ensureAccountingSchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $statements = [
        "ALTER TABLE event_registrations ADD COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER total_amount",
        "ALTER TABLE event_registrations ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER payment_status",
        "ALTER TABLE event_registrations ADD COLUMN payment_due_date DATE DEFAULT NULL AFTER amount_paid",
        "ALTER TABLE event_registrations ADD COLUMN payment_notes TEXT DEFAULT NULL AFTER payment_due_date",
        "CREATE TABLE IF NOT EXISTS expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            expense_date DATE NOT NULL,
            category VARCHAR(100) NOT NULL,
            vendor VARCHAR(150) DEFAULT NULL,
            description TEXT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
            linked_event_id INT DEFAULT NULL,
            payment_status VARCHAR(30) NOT NULL DEFAULT 'paid',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_expense_date (expense_date),
            INDEX idx_linked_event (linked_event_id)
        )",
        "CREATE TABLE IF NOT EXISTS finance_customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(180) NOT NULL,
            contact_person VARCHAR(150) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            phone VARCHAR(60) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            organization VARCHAR(180) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS finance_vendors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_name VARCHAR(180) NOT NULL,
            contact_person VARCHAR(150) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            phone VARCHAR(60) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS finance_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            invoice_date DATE NOT NULL,
            due_date DATE DEFAULT NULL,
            currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
            subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_finance_invoice_customer (customer_id)
        )",
        "CREATE TABLE IF NOT EXISTS finance_invoice_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            event_id INT DEFAULT NULL,
            description VARCHAR(255) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_finance_invoice_line_invoice (invoice_id)
        )",
        "CREATE TABLE IF NOT EXISTS finance_credit_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credit_note_number VARCHAR(50) NOT NULL UNIQUE,
            invoice_id INT NOT NULL,
            credit_date DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            reason TEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'issued',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_finance_credit_invoice (invoice_id)
        )",
        "CREATE TABLE IF NOT EXISTS vendor_bills (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bill_number VARCHAR(50) NOT NULL UNIQUE,
            vendor_id INT NOT NULL,
            bill_date DATE NOT NULL,
            due_date DATE DEFAULT NULL,
            currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
            subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_vendor_bill_vendor (vendor_id)
        )",
        "CREATE TABLE IF NOT EXISTS vendor_bill_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_bill_id INT NOT NULL,
            linked_event_id INT DEFAULT NULL,
            description VARCHAR(255) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_vendor_bill_line_bill (vendor_bill_id)
        )",
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore duplicate-column errors and continue with bootstrap.
        }
    }
}

function accountingCurrency(float $amount, string $currency = 'USD'): string
{
    return $currency . ' ' . number_format($amount, 2);
}

function generateFinanceNumber(string $prefix, int $id): string
{
    return $prefix . '-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function getPostedInvoiceTotal(PDO $pdo): float
{
    return (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount), 0) FROM finance_invoices WHERE status <> 'void'"
    )->fetchColumn();
}

function getPostedInvoiceCollected(PDO $pdo): float
{
    return (float) $pdo->query(
        "SELECT COALESCE(SUM(amount_paid), 0) FROM finance_invoices WHERE status <> 'void'"
    )->fetchColumn();
}

function getCreditNoteTotal(PDO $pdo): float
{
    return (float) $pdo->query(
        "SELECT COALESCE(SUM(amount), 0) FROM finance_credit_notes WHERE status <> 'void'"
    )->fetchColumn();
}

function getVendorBillTotal(PDO $pdo): float
{
    return (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount), 0) FROM vendor_bills WHERE status <> 'void'"
    )->fetchColumn();
}

function fetchAccountingSummary(PDO $pdo): array
{
    ensureAccountingSchema($pdo);

    $summary = [
        'total_revenue' => 0.0,
        'collected_revenue' => 0.0,
        'outstanding_revenue' => 0.0,
        'total_expenses' => 0.0,
        'net_income' => 0.0,
        'pending_invoices' => 0,
        'paid_registrations' => 0,
        'delegates' => 0,
        'average_invoice' => 0.0,
        'manual_invoices' => 0,
        'customers' => 0,
        'vendors' => 0,
    ];

    $registrationRevenue = $pdo->query(
        "SELECT
            COALESCE(SUM(total_amount), 0) AS total_revenue,
            COALESCE(SUM(amount_paid), 0) AS collected_revenue,
            COALESCE(SUM(CASE WHEN payment_status <> 'paid' THEN GREATEST(total_amount - amount_paid, 0) ELSE 0 END), 0) AS outstanding_revenue,
            COALESCE(SUM(attendee_count), 0) AS delegates,
            COALESCE(AVG(NULLIF(total_amount, 0)), 0) AS average_invoice,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_registrations,
            SUM(CASE WHEN payment_status <> 'paid' THEN 1 ELSE 0 END) AS pending_invoices,
            COUNT(*) AS registration_count
         FROM event_registrations"
    )->fetch();

    $manualInvoiceTotal = getPostedInvoiceTotal($pdo);
    $manualCollected = getPostedInvoiceCollected($pdo);
    $manualOutstanding = (float) $pdo->query(
        "SELECT COALESCE(SUM(GREATEST(total_amount - amount_paid, 0)), 0)
         FROM finance_invoices WHERE status NOT IN ('void', 'paid')"
    )->fetchColumn();
    $manualInvoiceCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM finance_invoices WHERE status <> 'void'"
    )->fetchColumn();
    $creditNoteTotal = getCreditNoteTotal($pdo);

    $regTotal = (float) ($registrationRevenue['total_revenue'] ?? 0);
    $regCollected = (float) ($registrationRevenue['collected_revenue'] ?? 0);
    $regOutstanding = (float) ($registrationRevenue['outstanding_revenue'] ?? 0);

    $summary['total_revenue'] = max(0, $regTotal + $manualInvoiceTotal - $creditNoteTotal);
    $summary['collected_revenue'] = $regCollected + $manualCollected;
    $summary['outstanding_revenue'] = $regOutstanding + $manualOutstanding;
    $summary['delegates'] = (int) ($registrationRevenue['delegates'] ?? 0);
    $summary['paid_registrations'] = (int) ($registrationRevenue['paid_registrations'] ?? 0);
    $summary['pending_invoices'] = (int) (($registrationRevenue['pending_invoices'] ?? 0) + $manualInvoiceCount);

    $invoiceCountBase = (int) ($registrationRevenue['registration_count'] ?? 0) + $manualInvoiceCount;
    $summary['average_invoice'] = $invoiceCountBase > 0 ? $summary['total_revenue'] / $invoiceCountBase : 0.0;
    $summary['manual_invoices'] = $manualInvoiceCount;

    $expenseTotal = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
    $vendorBillTotal = getVendorBillTotal($pdo);
    $summary['total_expenses'] = $expenseTotal + $vendorBillTotal;
    $summary['net_income'] = $summary['total_revenue'] - $summary['total_expenses'];
    $summary['customers'] = (int) $pdo->query("SELECT COUNT(*) FROM finance_customers")->fetchColumn();
    $summary['vendors'] = (int) $pdo->query("SELECT COUNT(*) FROM finance_vendors")->fetchColumn();

    return $summary;
}
