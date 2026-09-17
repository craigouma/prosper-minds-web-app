<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/invoice.php';
require_once '../includes/accounting.php';
requireAdminAuth();
requirePermission('accounting', 'view');

ensureRegistrationInvoiceSchema($pdo);
ensureAccountingSchema($pdo);

$pageTitle = 'Accounting';
$activePage = 'accounting';
$error = '';
$message = '';
$section = $_GET['section'] ?? 'overview';
$allowedSections = ['overview', 'customers', 'vendors', 'invoices', 'credit-notes', 'vendor-bills', 'expenses', 'collections', 'reporting'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'overview';
}

function sanitizeFinanceRows(array $descriptions, array $quantities, array $unitPrices, array $eventIds = []): array
{
    $rows = [];
    foreach ($descriptions as $idx => $description) {
        $description = trim((string) $description);
        $qty = (float) ($quantities[$idx] ?? 0);
        $price = (float) ($unitPrices[$idx] ?? 0);
        $eventId = (int) ($eventIds[$idx] ?? 0);

        if ($description === '' && $qty <= 0 && $price <= 0) {
            continue;
        }

        if ($description === '' || $qty <= 0) {
            throw new RuntimeException('Each line item must have a description and quantity.');
        }

        $rows[] = [
            'description' => $description,
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => $qty * $price,
            'event_id' => $eventId > 0 ? $eventId : null,
        ];
    }

    if (empty($rows)) {
        throw new RuntimeException('Please add at least one valid line item.');
    }

    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    requirePermission('accounting', 'edit');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $customerName = trim($_POST['customer_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $organization = trim($_POST['organization'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($customerName === '') {
            $error = 'Customer name is required.';
        } else {
            $pdo->prepare(
                "INSERT INTO finance_customers
                 (customer_name, contact_person, email, phone, address, organization, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$customerName, $contactPerson, $email, $phone, $address, $organization, $notes]);
            header('Location: accounting.php?section=customers&msg=customer_saved');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vendor'])) {
    requirePermission('accounting', 'edit');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $vendorName = trim($_POST['vendor_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($vendorName === '') {
            $error = 'Vendor name is required.';
        } else {
            $pdo->prepare(
                "INSERT INTO finance_vendors
                 (vendor_name, contact_person, email, phone, address, category, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$vendorName, $contactPerson, $email, $phone, $address, $category, $notes]);
            header('Location: accounting.php?section=vendors&msg=vendor_saved');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    requirePermission('accounting', 'edit');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $invoiceDate = trim($_POST['invoice_date'] ?? '');
            $dueDate = trim($_POST['due_date'] ?? '');
            $currencyCode = strtoupper(trim($_POST['currency_code'] ?? 'USD'));
            $status = trim($_POST['status'] ?? 'draft');
            $notes = trim($_POST['notes'] ?? '');
            $taxAmount = (float) ($_POST['tax_amount'] ?? 0);

            if ($customerId <= 0 || $invoiceDate === '') {
                throw new RuntimeException('Customer and invoice date are required.');
            }

            $rows = sanitizeFinanceRows(
                $_POST['line_description'] ?? [],
                $_POST['line_qty'] ?? [],
                $_POST['line_price'] ?? [],
                $_POST['line_event_id'] ?? []
            );

            $subtotal = array_sum(array_column($rows, 'line_total'));
            $total = $subtotal + $taxAmount;

            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO finance_invoices
                 (invoice_number, customer_id, invoice_date, due_date, currency_code, subtotal_amount, tax_amount, total_amount, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                'TMP-' . bin2hex(random_bytes(4)),
                $customerId,
                $invoiceDate,
                $dueDate !== '' ? $dueDate : null,
                $currencyCode ?: 'USD',
                $subtotal,
                $taxAmount,
                $total,
                $status,
                $notes,
            ]);

            $invoiceId = (int) $pdo->lastInsertId();
            $invoiceNumber = generateFinanceNumber('INV', $invoiceId);
            $pdo->prepare("UPDATE finance_invoices SET invoice_number = ? WHERE id = ?")->execute([$invoiceNumber, $invoiceId]);

            $lineStmt = $pdo->prepare(
                "INSERT INTO finance_invoice_lines
                 (invoice_id, event_id, description, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($rows as $row) {
                $lineStmt->execute([$invoiceId, $row['event_id'], $row['description'], $row['quantity'], $row['unit_price'], $row['line_total']]);
            }

            $pdo->commit();
            header('Location: accounting.php?section=invoices&msg=invoice_saved');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_credit_note'])) {
    requirePermission('accounting', 'edit');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $creditDate = trim($_POST['credit_date'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $status = trim($_POST['status'] ?? 'issued');

        if ($invoiceId <= 0 || $creditDate === '' || $amount <= 0) {
            $error = 'Invoice, credit date and amount are required.';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO finance_credit_notes
                 (credit_note_number, invoice_id, credit_date, amount, reason, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                'TMP-' . bin2hex(random_bytes(4)),
                $invoiceId,
                $creditDate,
                $amount,
                $reason,
                $status,
            ]);
            $creditId = (int) $pdo->lastInsertId();
            $creditNumber = generateFinanceNumber('CRN', $creditId);
            $pdo->prepare("UPDATE finance_credit_notes SET credit_note_number = ? WHERE id = ?")->execute([$creditNumber, $creditId]);
            $pdo->commit();
            header('Location: accounting.php?section=credit-notes&msg=credit_saved');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vendor_bill'])) {
    requirePermission('accounting', 'edit');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $vendorId = (int) ($_POST['vendor_id'] ?? 0);
            $billDate = trim($_POST['bill_date'] ?? '');
            $dueDate = trim($_POST['due_date'] ?? '');
            $currencyCode = strtoupper(trim($_POST['currency_code'] ?? 'USD'));
            $status = trim($_POST['status'] ?? 'open');
            $notes = trim($_POST['notes'] ?? '');
            $taxAmount = (float) ($_POST['tax_amount'] ?? 0);

            if ($vendorId <= 0 || $billDate === '') {
                throw new RuntimeException('Vendor and bill date are required.');
            }

            $rows = sanitizeFinanceRows(
                $_POST['bill_line_description'] ?? [],
                $_POST['bill_line_qty'] ?? [],
                $_POST['bill_line_price'] ?? [],
                $_POST['bill_line_event_id'] ?? []
            );

            $subtotal = array_sum(array_column($rows, 'line_total'));
            $total = $subtotal + $taxAmount;

            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO vendor_bills
                 (bill_number, vendor_id, bill_date, due_date, currency_code, subtotal_amount, tax_amount, total_amount, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                'TMP-' . bin2hex(random_bytes(4)),
                $vendorId,
                $billDate,
                $dueDate !== '' ? $dueDate : null,
                $currencyCode ?: 'USD',
                $subtotal,
                $taxAmount,
                $total,
                $status,
                $notes,
            ]);

            $billId = (int) $pdo->lastInsertId();
            $billNumber = generateFinanceNumber('BILL', $billId);
            $pdo->prepare("UPDATE vendor_bills SET bill_number = ? WHERE id = ?")->execute([$billNumber, $billId]);

            $lineStmt = $pdo->prepare(
                "INSERT INTO vendor_bill_lines
                 (vendor_bill_id, linked_event_id, description, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($rows as $row) {
                $lineStmt->execute([$billId, $row['event_id'], $row['description'], $row['quantity'], $row['unit_price'], $row['line_total']]);
            }

            $pdo->commit();
            header('Location: accounting.php?section=vendor-bills&msg=bill_saved');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    requirePermission('accounting', 'expenses');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $expenseDate = trim($_POST['expense_date'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $vendor = trim($_POST['vendor'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $currencyCode = trim($_POST['currency_code'] ?? 'USD') ?: 'USD';
        $linkedEventId = (int) ($_POST['linked_event_id'] ?? 0);
        $paymentStatus = trim($_POST['payment_status'] ?? 'paid');
        $notes = trim($_POST['notes'] ?? '');

        if ($expenseDate === '' || $category === '' || $description === '' || $amount <= 0) {
            $error = 'Expense date, category, description and amount are required.';
        } else {
            $pdo->prepare(
                "INSERT INTO expenses
                 (expense_date, category, vendor, description, amount, currency_code, linked_event_id, payment_status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $expenseDate, $category, $vendor, $description, $amount, strtoupper($currencyCode), $linkedEventId > 0 ? $linkedEventId : null, $paymentStatus, $notes
            ]);
            header('Location: accounting.php?section=expenses&msg=expense_saved');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    requirePermission('accounting', 'edit');
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $registrationId = (int) ($_POST['registration_id'] ?? 0);
        $paymentStatus = trim($_POST['payment_status'] ?? 'pending');
        $amountPaid = (float) ($_POST['amount_paid'] ?? 0);
        $paymentDueDate = trim($_POST['payment_due_date'] ?? '');
        $paymentNotes = trim($_POST['payment_notes'] ?? '');

        $pdo->prepare(
            "UPDATE event_registrations
             SET payment_status = ?, amount_paid = ?, payment_due_date = ?, payment_notes = ?
             WHERE id = ?"
        )->execute([$paymentStatus, max(0, $amountPaid), $paymentDueDate !== '' ? $paymentDueDate : null, $paymentNotes !== '' ? $paymentNotes : null, $registrationId]);

        header('Location: accounting.php?section=collections&msg=payment_updated');
        exit;
    }
    $error = 'Invalid security token.';
}

if (isset($_GET['msg'])) {
    $flash = [
        'customer_saved' => 'Customer saved successfully.',
        'vendor_saved' => 'Vendor saved successfully.',
        'invoice_saved' => 'Invoice created successfully.',
        'credit_saved' => 'Credit note created successfully.',
        'bill_saved' => 'Vendor bill created successfully.',
        'expense_saved' => 'Expense saved successfully.',
        'payment_updated' => 'Payment status updated successfully.',
    ];
    $message = $flash[$_GET['msg']] ?? '';
}

$summary = fetchAccountingSummary($pdo);
$collectionRate = $summary['total_revenue'] > 0 ? ($summary['collected_revenue'] / $summary['total_revenue']) * 100 : 0;

$customers = $pdo->query("SELECT * FROM finance_customers ORDER BY id DESC")->fetchAll();
$vendors = $pdo->query("SELECT * FROM finance_vendors ORDER BY id DESC")->fetchAll();
$events = $pdo->query("SELECT id, title, location, date_display, price FROM events ORDER BY event_start_date, id")->fetchAll();
$invoices = $pdo->query(
    "SELECT fi.*, fc.customer_name
     FROM finance_invoices fi
     LEFT JOIN finance_customers fc ON fc.id = fi.customer_id
     ORDER BY fi.id DESC"
)->fetchAll();
$creditNotes = $pdo->query(
    "SELECT cn.*, fi.invoice_number, fc.customer_name
     FROM finance_credit_notes cn
     LEFT JOIN finance_invoices fi ON fi.id = cn.invoice_id
     LEFT JOIN finance_customers fc ON fc.id = fi.customer_id
     ORDER BY cn.id DESC"
)->fetchAll();
$vendorBills = $pdo->query(
    "SELECT vb.*, fv.vendor_name
     FROM vendor_bills vb
     LEFT JOIN finance_vendors fv ON fv.id = vb.vendor_id
     ORDER BY vb.id DESC"
)->fetchAll();
$recentExpenses = $pdo->query(
    "SELECT ex.*, ev.title AS event_title
     FROM expenses ex
     LEFT JOIN events ev ON ev.id = ex.linked_event_id
     ORDER BY ex.expense_date DESC, ex.id DESC
     LIMIT 12"
)->fetchAll();
$paymentPipeline = $pdo->query(
    "SELECT id, first_name, last_name, organization, event_name, total_amount, amount_paid, payment_status, payment_due_date
     FROM event_registrations
     ORDER BY created_at DESC
     LIMIT 12"
)->fetchAll();
$monthlyPerformance = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            DATE_FORMAT(created_at, '%b %Y') AS month_label,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(SUM(amount_paid), 0) AS collected,
            COUNT(*) AS registrations,
            COALESCE(SUM(attendee_count), 0) AS delegates
     FROM event_registrations
     GROUP BY month_key, month_label
     ORDER BY month_key DESC
     LIMIT 6"
)->fetchAll();
$expenseByCategory = $pdo->query(
    "SELECT category, COALESCE(SUM(amount), 0) AS total
     FROM expenses
     GROUP BY category
     ORDER BY total DESC"
)->fetchAll();
$eventProfitability = $pdo->query(
    "SELECT e.id, e.title, e.location, e.date_display,
            COALESCE(SUM(r.total_amount), 0) AS registration_revenue,
            COALESCE(ex.expense_total, 0) AS direct_expenses
     FROM events e
     LEFT JOIN event_registrations r ON r.event_id = e.id
     LEFT JOIN (
        SELECT linked_event_id, SUM(amount) AS expense_total
        FROM expenses
        WHERE linked_event_id IS NOT NULL
        GROUP BY linked_event_id
     ) ex ON ex.linked_event_id = e.id
     GROUP BY e.id, e.title, e.location, e.date_display, ex.expense_total
     ORDER BY registration_revenue DESC"
)->fetchAll();

$cfoInsights = [
    'Collection rate is ' . number_format($collectionRate, 1) . '%.',
    'Outstanding invoices total ' . accountingCurrency($summary['outstanding_revenue']) . '.',
    'Manual invoices in system: ' . (int) $summary['manual_invoices'] . '.',
    'Customers on record: ' . (int) $summary['customers'] . '. Vendors on record: ' . (int) $summary['vendors'] . '.',
];

include 'header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="finance-shell">
    <div class="finance-topbar finance-topbar-compact">
        <div class="finance-brand">
            <div class="finance-brand-icon"><i class="fas fa-book"></i></div>
            <div>
                <strong>Prosperminds Finance</strong>
                <span>Sales, payables and reporting workspace</span>
            </div>
        </div>
        <nav class="finance-menu finance-menu-dropdown">
            <details class="finance-dropdown" <?php echo in_array($section, ['overview', 'collections', 'reporting'], true) ? 'open' : ''; ?>>
                <summary class="finance-menu-item">Dashboard</summary>
                <div class="finance-dropdown-panel">
                    <a href="accounting.php?section=overview">Executive Summary</a>
                    <a href="accounting.php?section=collections">Collections Pipeline</a>
                    <a href="accounting.php?section=reporting">Reporting</a>
                </div>
            </details>
            <details class="finance-dropdown" <?php echo in_array($section, ['customers', 'invoices', 'credit-notes'], true) ? 'open' : ''; ?>>
                <summary class="finance-menu-item">Sales</summary>
                <div class="finance-dropdown-panel">
                    <a href="accounting.php?section=customers">Customers</a>
                    <a href="accounting.php?section=invoices">Invoices</a>
                    <a href="accounting.php?section=credit-notes">Credit Notes</a>
                </div>
            </details>
            <details class="finance-dropdown" <?php echo in_array($section, ['vendors', 'vendor-bills', 'expenses'], true) ? 'open' : ''; ?>>
                <summary class="finance-menu-item">Purchasing</summary>
                <div class="finance-dropdown-panel">
                    <a href="accounting.php?section=vendors">Vendors</a>
                    <a href="accounting.php?section=vendor-bills">Vendor Bills</a>
                    <a href="accounting.php?section=expenses">Expenses</a>
                </div>
            </details>
        </nav>
    </div>

    <div class="stats-grid" style="margin-top:18px;">
        <div class="stat-card"><div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div><div class="stat-info"><h3><?php echo accountingCurrency($summary['total_revenue']); ?></h3><p>Total Invoiced Revenue</p></div></div>
        <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-wallet"></i></div><div class="stat-info"><h3><?php echo accountingCurrency($summary['collected_revenue']); ?></h3><p>Cash Collected</p></div></div>
        <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-file-invoice-dollar"></i></div><div class="stat-info"><h3><?php echo accountingCurrency($summary['outstanding_revenue']); ?></h3><p>Outstanding Pipeline</p></div></div>
        <div class="stat-card"><div class="stat-icon red"><i class="fas fa-scale-balanced"></i></div><div class="stat-info"><h3><?php echo accountingCurrency($summary['net_income']); ?></h3><p>Net Income After Expenses</p></div></div>
    </div>

    <?php if ($section === 'overview'): ?>
        <div style="display:grid;grid-template-columns:1.35fr 1fr;gap:24px;align-items:start;">
            <div class="table-card">
                <div class="table-card-header">
                    <div><div class="card-title">Executive Summary</div><div class="card-subtitle">CFO / CEO snapshot of registrations, cash and growth</div></div>
                </div>
                <div style="padding:20px;">
                    <div class="kpi-strip">
                        <div class="mini-kpi"><span>Delegates</span><strong><?php echo (int) $summary['delegates']; ?></strong></div>
                        <div class="mini-kpi"><span>Pending Invoices</span><strong><?php echo (int) $summary['pending_invoices']; ?></strong></div>
                        <div class="mini-kpi"><span>Customers</span><strong><?php echo (int) $summary['customers']; ?></strong></div>
                        <div class="mini-kpi"><span>Vendors</span><strong><?php echo (int) $summary['vendors']; ?></strong></div>
                    </div>
                    <div class="insight-board">
                        <?php foreach ($cfoInsights as $insight): ?>
                            <div class="insight-item"><i class="fas fa-lightbulb"></i><span><?php echo htmlspecialchars($insight); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="table-card">
                <div class="table-card-header">
                    <div><div class="card-title">Recent Finance Activity</div><div class="card-subtitle">Latest invoices, bills and expenses</div></div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Type</th><th>Reference</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($invoices, 0, 4) as $row): ?>
                                <tr><td>Invoice</td><td><?php echo htmlspecialchars($row['invoice_number']); ?><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($row['customer_name'] ?: '-'); ?></span></td><td><?php echo accountingCurrency((float) $row['total_amount'], $row['currency_code']); ?></td></tr>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($vendorBills, 0, 3) as $row): ?>
                                <tr><td>Vendor Bill</td><td><?php echo htmlspecialchars($row['bill_number']); ?><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($row['vendor_name'] ?: '-'); ?></span></td><td><?php echo accountingCurrency((float) $row['total_amount'], $row['currency_code']); ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (empty($invoices) && empty($vendorBills)): ?>
                                <tr><td colspan="3" class="empty-state"><i class="fas fa-folder-open"></i>No finance records yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'customers'): ?>
        <div style="display:grid;grid-template-columns:.95fr 1.05fr;gap:24px;align-items:start;">
            <div class="card">
                <div class="card-title">Add Customer</div>
                <div class="card-subtitle" style="margin-bottom:16px;">Create a customer record for manual invoicing</div>
                <form method="POST" action="accounting.php?section=customers">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_customer" value="1">
                    <div class="form-group"><label>Customer Name *</label><input type="text" name="customer_name" class="form-control" required></div>
                    <div class="form-grid">
                        <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" class="form-control"></div>
                        <div class="form-group"><label>Organization</label><input type="text" name="organization" class="form-control"></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Address</label><textarea name="address" class="form-control"></textarea></div>
                    <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </form>
            </div>
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Customers</div><div class="card-subtitle">Finance master data</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Name</th><th>Contact</th><th>Address</th></tr></thead>
                        <tbody>
                            <?php if ($customers): foreach ($customers as $customer): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($customer['organization'] ?: '-'); ?></span></td>
                                    <td><?php echo htmlspecialchars($customer['contact_person'] ?: '-'); ?><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($customer['email'] ?: ($customer['phone'] ?: '-')); ?></span></td>
                                    <td><?php echo nl2br(htmlspecialchars($customer['address'] ?: '-')); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="empty-state"><i class="fas fa-user-plus"></i>No customers yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'vendors'): ?>
        <div style="display:grid;grid-template-columns:.95fr 1.05fr;gap:24px;align-items:start;">
            <div class="card">
                <div class="card-title">Add Vendor</div>
                <div class="card-subtitle" style="margin-bottom:16px;">Create supplier records for bills and expenses</div>
                <form method="POST" action="accounting.php?section=vendors">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_vendor" value="1">
                    <div class="form-group"><label>Vendor Name *</label><input type="text" name="vendor_name" class="form-control" required></div>
                    <div class="form-grid">
                        <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" class="form-control"></div>
                        <div class="form-group"><label>Category</label><input type="text" name="category" class="form-control" placeholder="Travel, Venue, etc."></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Address</label><textarea name="address" class="form-control"></textarea></div>
                    <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Vendor</button>
                </form>
            </div>
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Vendors</div><div class="card-subtitle">Supplier master data</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Name</th><th>Category</th><th>Contact</th></tr></thead>
                        <tbody>
                            <?php if ($vendors): foreach ($vendors as $vendor): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($vendor['vendor_name']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($vendor['contact_person'] ?: '-'); ?></span></td>
                                    <td><?php echo htmlspecialchars($vendor['category'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['email'] ?: '-'); ?><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($vendor['phone'] ?: '-'); ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="empty-state"><i class="fas fa-truck"></i>No vendors yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'invoices'): ?>
        <div style="display:grid;grid-template-columns:1.15fr .85fr;gap:24px;align-items:start;">
            <div class="card">
                <div class="card-title">Create Invoice</div>
                <div class="card-subtitle" style="margin-bottom:16px;">Build real sales invoices with line items and linked events</div>
                <form method="POST" action="accounting.php?section=invoices" id="invoiceForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_invoice" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Customer *</label>
                            <select name="customer_id" class="form-control" required>
                                <option value="">Select customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo (int) $customer['id']; ?>"><?php echo htmlspecialchars($customer['customer_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="draft">Draft</option>
                                <option value="sent">Sent</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Invoice Date *</label><input type="date" name="invoice_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control"></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Currency</label><input type="text" name="currency_code" class="form-control" value="USD"></div>
                        <div class="form-group"><label>Tax Amount</label><input type="number" step="0.01" min="0" name="tax_amount" class="form-control" value="0.00"></div>
                    </div>
                    <div class="finance-lines-card">
                        <div class="finance-lines-header">
                            <strong>Invoice Lines</strong>
                            <button type="button" class="btn btn-outline btn-sm" onclick="addFinanceLine('invoiceLinesBody','invoice')">Add Line</button>
                        </div>
                        <div id="invoiceLinesBody"></div>
                    </div>
                    <div class="form-group" style="margin-top:16px;"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </form>
            </div>
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Invoices</div><div class="card-subtitle">Created in backend</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Invoice</th><th>Status</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php if ($invoices): foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($invoice['customer_name'] ?: '-'); ?></span></td>
                                <td><span class="badge <?php echo $invoice['status'] === 'paid' ? 'badge-green' : ($invoice['status'] === 'draft' ? 'badge-gray' : 'badge-orange'); ?>"><?php echo htmlspecialchars(ucfirst($invoice['status'])); ?></span></td>
                                <td><?php echo accountingCurrency((float) $invoice['total_amount'], $invoice['currency_code']); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="empty-state"><i class="fas fa-file-invoice-dollar"></i>No invoices yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'credit-notes'): ?>
        <div style="display:grid;grid-template-columns:.9fr 1.1fr;gap:24px;align-items:start;">
            <div class="card">
                <div class="card-title">Issue Credit Note</div>
                <div class="card-subtitle" style="margin-bottom:16px;">Create a credit note against a backend invoice</div>
                <form method="POST" action="accounting.php?section=credit-notes">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_credit_note" value="1">
                    <div class="form-group">
                        <label>Invoice *</label>
                        <select name="invoice_id" class="form-control" required>
                            <option value="">Select invoice</option>
                            <?php foreach ($invoices as $invoice): ?>
                                <option value="<?php echo (int) $invoice['id']; ?>"><?php echo htmlspecialchars($invoice['invoice_number'] . ' - ' . ($invoice['customer_name'] ?: 'Customer')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Credit Date *</label><input type="date" name="credit_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="form-group"><label>Amount *</label><input type="number" step="0.01" min="0" name="amount" class="form-control" required></div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="issued">Issued</option>
                            <option value="applied">Applied</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Reason</label><textarea name="reason" class="form-control"></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Credit Note</button>
                </form>
            </div>
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Credit Notes</div><div class="card-subtitle">All issued credits</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Credit Note</th><th>Invoice</th><th>Amount</th></tr></thead>
                        <tbody>
                        <?php if ($creditNotes): foreach ($creditNotes as $note): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($note['credit_note_number']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars(date('M d, Y', strtotime($note['credit_date']))); ?></span></td>
                                <td><?php echo htmlspecialchars(($note['invoice_number'] ?: '-') . ' / ' . ($note['customer_name'] ?: '-')); ?></td>
                                <td><?php echo accountingCurrency((float) $note['amount']); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="empty-state"><i class="fas fa-reply"></i>No credit notes yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'vendor-bills'): ?>
        <div style="display:grid;grid-template-columns:1.15fr .85fr;gap:24px;align-items:start;">
            <div class="card">
                <div class="card-title">Create Vendor Bill</div>
                <div class="card-subtitle" style="margin-bottom:16px;">Capture supplier bills with bill lines and event references</div>
                <form method="POST" action="accounting.php?section=vendor-bills">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_vendor_bill" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Vendor *</label>
                            <select name="vendor_id" class="form-control" required>
                                <option value="">Select vendor</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?php echo (int) $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['vendor_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="open">Open</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Bill Date *</label><input type="date" name="bill_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control"></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Currency</label><input type="text" name="currency_code" class="form-control" value="USD"></div>
                        <div class="form-group"><label>Tax Amount</label><input type="number" step="0.01" min="0" name="tax_amount" class="form-control" value="0.00"></div>
                    </div>
                    <div class="finance-lines-card">
                        <div class="finance-lines-header">
                            <strong>Bill Lines</strong>
                            <button type="button" class="btn btn-outline btn-sm" onclick="addFinanceLine('billLinesBody','bill')">Add Line</button>
                        </div>
                        <div id="billLinesBody"></div>
                    </div>
                    <div class="form-group" style="margin-top:16px;"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
                    <button type="submit" class="btn btn-primary">Create Vendor Bill</button>
                </form>
            </div>
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Vendor Bills</div><div class="card-subtitle">AP records</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Bill</th><th>Status</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php if ($vendorBills): foreach ($vendorBills as $bill): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($bill['vendor_name'] ?: '-'); ?></span></td>
                                <td><span class="badge <?php echo $bill['status'] === 'paid' ? 'badge-green' : ($bill['status'] === 'open' ? 'badge-orange' : 'badge-gray'); ?>"><?php echo htmlspecialchars(ucfirst($bill['status'])); ?></span></td>
                                <td><?php echo accountingCurrency((float) $bill['total_amount'], $bill['currency_code']); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="empty-state"><i class="fas fa-file-invoice"></i>No vendor bills yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'expenses'): ?>
        <div style="display:grid;grid-template-columns:.95fr 1.05fr;gap:24px;align-items:start;">
            <div class="card">
                <div class="card-title">Add Expense</div>
                <div class="card-subtitle" style="margin-bottom:16px;">Capture operating costs and event spend</div>
                <form method="POST" action="accounting.php?section=expenses">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_expense" value="1">
                    <div class="form-group"><label>Expense Date *</label><input type="date" name="expense_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="form-group"><label>Category *</label><input type="text" name="category" class="form-control" required placeholder="Travel, Venue, Marketing..."></div>
                    <div class="form-group"><label>Vendor</label><input type="text" name="vendor" class="form-control"></div>
                    <div class="form-group"><label>Description *</label><textarea name="description" class="form-control" required></textarea></div>
                    <div class="form-grid">
                        <div class="form-group"><label>Amount *</label><input type="number" step="0.01" min="0" name="amount" class="form-control" required></div>
                        <div class="form-group"><label>Currency</label><input type="text" name="currency_code" class="form-control" value="USD"></div>
                    </div>
                    <div class="form-group">
                        <label>Linked Event</label>
                        <select name="linked_event_id" class="form-control">
                            <option value="">Not event specific</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo (int) $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="paid">Paid</option>
                            <option value="planned">Planned</option>
                            <option value="accrued">Accrued</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </form>
            </div>
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Recent Expenses</div><div class="card-subtitle">Latest operating cost entries</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Date</th><th>Category</th><th>Amount</th></tr></thead>
                        <tbody>
                        <?php if ($recentExpenses): foreach ($recentExpenses as $expense): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($expense['vendor'] ?: ($expense['event_title'] ?: '-')); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($expense['category']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($expense['description']); ?></span></td>
                                <td><?php echo accountingCurrency((float) $expense['amount'], $expense['currency_code']); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="empty-state"><i class="fas fa-credit-card"></i>No expenses recorded</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'collections'): ?>
        <div class="table-card">
            <div class="table-card-header"><div><div class="card-title">Collections Pipeline</div><div class="card-subtitle">What finance should follow up on now</div></div></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Customer</th><th>Event</th><th>Status</th><th>Outstanding</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if ($paymentPipeline): foreach ($paymentPipeline as $invoice): ?>
                        <?php $outstanding = max(0, (float) $invoice['total_amount'] - (float) $invoice['amount_paid']); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($invoice['organization'] ?: '-'); ?></span></td>
                            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($invoice['event_name']); ?>"><?php echo htmlspecialchars($invoice['event_name']); ?></td>
                            <td><span class="badge <?php echo $invoice['payment_status'] === 'paid' ? 'badge-green' : ($invoice['payment_status'] === 'partial' ? 'badge-orange' : 'badge-red'); ?>"><?php echo htmlspecialchars(ucfirst($invoice['payment_status'])); ?></span></td>
                            <td><?php echo accountingCurrency($outstanding); ?></td>
                            <td>
                                <details>
                                    <summary class="btn btn-outline btn-sm">Update</summary>
                                    <form method="POST" action="accounting.php?section=collections" style="margin-top:10px;min-width:240px;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="update_payment" value="1">
                                        <input type="hidden" name="registration_id" value="<?php echo (int) $invoice['id']; ?>">
                                        <div class="form-group"><label>Status</label><select name="payment_status" class="form-control"><option value="pending" <?php echo $invoice['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="partial" <?php echo $invoice['payment_status'] === 'partial' ? 'selected' : ''; ?>>Partial</option><option value="paid" <?php echo $invoice['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option><option value="overdue" <?php echo $invoice['payment_status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option></select></div>
                                        <div class="form-group"><label>Amount Paid</label><input type="number" step="0.01" min="0" name="amount_paid" class="form-control" value="<?php echo htmlspecialchars((string) $invoice['amount_paid']); ?>"></div>
                                        <div class="form-group"><label>Due Date</label><input type="date" name="payment_due_date" class="form-control" value="<?php echo htmlspecialchars($invoice['payment_due_date'] ?? ''); ?>"></div>
                                        <div class="form-group"><label>Notes</label><textarea name="payment_notes" class="form-control" rows="3"></textarea></div>
                                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="empty-state"><i class="fas fa-money-bill-wave"></i>No invoices yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Monthly Performance</div><div class="card-subtitle">Revenue, collections and delegate trends</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Month</th><th>Revenue</th><th>Collected</th><th>Regs</th><th>Delegates</th></tr></thead>
                        <tbody>
                        <?php if ($monthlyPerformance): foreach ($monthlyPerformance as $month): ?>
                            <tr><td><?php echo htmlspecialchars($month['month_label']); ?></td><td><?php echo accountingCurrency((float) $month['revenue']); ?></td><td><?php echo accountingCurrency((float) $month['collected']); ?></td><td><?php echo (int) $month['registrations']; ?></td><td><?php echo (int) $month['delegates']; ?></td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="empty-state"><i class="fas fa-chart-column"></i>No performance data yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="table-card">
                <div class="table-card-header"><div><div class="card-title">Event Profitability</div><div class="card-subtitle">Revenue against direct event expenses</div></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Event</th><th>Revenue</th><th>Expenses</th><th>Contribution</th></tr></thead>
                        <tbody>
                        <?php if ($eventProfitability): foreach ($eventProfitability as $eventRow): ?>
                            <?php $contribution = (float) $eventRow['registration_revenue'] - (float) $eventRow['direct_expenses']; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($eventRow['title']); ?></strong><br><span style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($eventRow['location'] . ' | ' . $eventRow['date_display']); ?></span></td>
                                <td><?php echo accountingCurrency((float) $eventRow['registration_revenue']); ?></td>
                                <td><?php echo accountingCurrency((float) $eventRow['direct_expenses']); ?></td>
                                <td class="<?php echo $contribution >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo accountingCurrency($contribution); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="empty-state"><i class="fas fa-briefcase"></i>No profitability data yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function financeLineRow(type) {
    const eventOptions = <?php echo json_encode(array_map(static function ($event) {
        return ['id' => (int) $event['id'], 'label' => $event['title'] . ' - ' . $event['location']];
    }, $events)); ?>;
    const eventFieldName = type === 'invoice' ? 'line_event_id[]' : 'bill_line_event_id[]';
    const descFieldName = type === 'invoice' ? 'line_description[]' : 'bill_line_description[]';
    const qtyFieldName = type === 'invoice' ? 'line_qty[]' : 'bill_line_qty[]';
    const priceFieldName = type === 'invoice' ? 'line_price[]' : 'bill_line_price[]';
    const optionsHtml = ['<option value="">General line</option>'].concat(eventOptions.map(event => `<option value="${event.id}">${event.label}</option>`)).join('');

    return `
        <div class="finance-line-row">
            <select name="${eventFieldName}" class="form-control">${optionsHtml}</select>
            <input type="text" name="${descFieldName}" class="form-control" placeholder="Description" required>
            <input type="number" step="0.01" min="0.01" name="${qtyFieldName}" class="form-control" placeholder="Qty" value="1" required>
            <input type="number" step="0.01" min="0" name="${priceFieldName}" class="form-control" placeholder="Unit Price" value="0.00" required>
            <button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()">Remove</button>
        </div>
    `;
}

function addFinanceLine(targetId, type) {
    const target = document.getElementById(targetId);
    if (!target) return;
    target.insertAdjacentHTML('beforeend', financeLineRow(type));
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('invoiceLinesBody')) {
        addFinanceLine('invoiceLinesBody', 'invoice');
    }
    if (document.getElementById('billLinesBody')) {
        addFinanceLine('billLinesBody', 'bill');
    }

    const financeDropdowns = document.querySelectorAll('.finance-dropdown');
    financeDropdowns.forEach(dropdown => {
        dropdown.addEventListener('toggle', function() {
            if (!this.open) {
                return;
            }

            financeDropdowns.forEach(other => {
                if (other !== this) {
                    other.removeAttribute('open');
                }
            });
        });
    });

    document.addEventListener('click', function(event) {
        if (event.target.closest('.finance-dropdown')) {
            return;
        }

        financeDropdowns.forEach(dropdown => dropdown.removeAttribute('open'));
    });
});
</script>

<?php include 'footer.php'; ?>
