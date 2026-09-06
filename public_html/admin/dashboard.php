<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/invoice.php';
require_once '../includes/accounting.php';
requireAdminAuth();

ensureRegistrationInvoiceSchema($pdo);
ensureAccountingSchema($pdo);

$pageTitle  = 'Overview';
$activePage = 'dashboard';

$totalRegs   = (int) $pdo->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn();
$totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events WHERE is_active = 1")->fetchColumn();
$thisMonth   = (int) $pdo->query(
    "SELECT COUNT(*) FROM event_registrations
     WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
)->fetchColumn();
$thisWeek    = (int) $pdo->query(
    "SELECT COUNT(*) FROM event_registrations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetchColumn();

$eventBreakdown = $pdo->query(
    "SELECT event_name, COUNT(*) AS total
     FROM event_registrations
     GROUP BY event_name
     ORDER BY total DESC"
)->fetchAll();

$recent = $pdo->query(
    "SELECT * FROM event_registrations ORDER BY id DESC LIMIT 10"
)->fetchAll();

$summary = fetchAccountingSummary($pdo);

$paymentPipeline = $pdo->query(
    "SELECT first_name, last_name, organization, event_name, total_amount, amount_paid, payment_status
     FROM event_registrations
     ORDER BY created_at DESC
     LIMIT 6"
)->fetchAll();

$expenseByCategory = $pdo->query(
    "SELECT category, COALESCE(SUM(amount), 0) AS total
     FROM expenses
     GROUP BY category
     ORDER BY total DESC
     LIMIT 5"
)->fetchAll();

$monthlyPerformance = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(SUM(amount_paid), 0) AS collected
     FROM event_registrations
     GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
     ORDER BY DATE_FORMAT(created_at, '%Y-%m') DESC
     LIMIT 4"
)->fetchAll();

$eventProfitability = $pdo->query(
    "SELECT e.title,
            e.location,
            e.date_display,
            COALESCE(SUM(r.total_amount), 0) AS revenue,
            COALESCE(SUM(r.amount_paid), 0) AS collected,
            COALESCE(SUM(r.attendee_count), 0) AS delegates,
            COALESCE(ex.expense_total, 0) AS expenses
     FROM events e
     LEFT JOIN event_registrations r ON r.event_id = e.id
     LEFT JOIN (
         SELECT linked_event_id, SUM(amount) AS expense_total
         FROM expenses
         WHERE linked_event_id IS NOT NULL
         GROUP BY linked_event_id
     ) ex ON ex.linked_event_id = e.id
     GROUP BY e.id, e.title, e.location, e.date_display, ex.expense_total
     ORDER BY revenue DESC, delegates DESC
     LIMIT 4"
)->fetchAll();

$collectionRate = $summary['total_revenue'] > 0 ? ($summary['collected_revenue'] / $summary['total_revenue']) * 100 : 0;
$cfoInsights = [];
$cfoInsights[] = 'Collection rate is ' . number_format($collectionRate, 1) . '%.';
$cfoInsights[] = 'Outstanding pipeline stands at ' . accountingCurrency($summary['outstanding_revenue']) . '.';
if (!empty($eventProfitability)) {
    $topEvent = $eventProfitability[0];
    if ((float) $topEvent['revenue'] > 0) {
        $cfoInsights[] = 'Top grossing event is ' . $topEvent['title'] . ' at ' . accountingCurrency((float) $topEvent['revenue']) . '.';
    }
}
if (!empty($expenseByCategory)) {
    $largestExpense = $expenseByCategory[0];
    $cfoInsights[] = 'Largest expense category is ' . $largestExpense['category'] . ' at ' . accountingCurrency((float) $largestExpense['total']) . '.';
}
$cfoInsights[] = 'Average invoice value is ' . accountingCurrency($summary['average_invoice']) . '.';

include 'header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalRegs; ?></h3>
            <p>Total Registrations</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalEvents; ?></h3>
            <p>Active Events</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-info">
            <h3><?php echo $thisMonth; ?></h3>
            <p>Registrations This Month</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <h3><?php echo $thisWeek; ?></h3>
            <p>Last 7 Days</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div>
        <div class="stat-info">
            <h3><?php echo accountingCurrency($summary['total_revenue']); ?></h3>
            <p>Total Invoiced Revenue</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <h3><?php echo accountingCurrency($summary['collected_revenue']); ?></h3>
            <p>Cash Collected</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-info">
            <h3><?php echo accountingCurrency($summary['outstanding_revenue']); ?></h3>
            <p>Outstanding Pipeline</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-scale-balanced"></i></div>
        <div class="stat-info">
            <h3><?php echo accountingCurrency($summary['net_income']); ?></h3>
            <p>Net Income After Expenses</p>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.55fr 1fr;gap:24px;align-items:start;">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Executive Summary</div>
                <div class="card-subtitle">CFO / CEO snapshot of registrations, cash and growth</div>
            </div>
            <a href="accounting.php" class="btn btn-outline btn-sm">Open Accounting</a>
        </div>
        <div style="padding:20px;">
            <div class="kpi-strip">
                <div class="mini-kpi">
                    <span>Delegates</span>
                    <strong><?php echo (int) $summary['delegates']; ?></strong>
                </div>
                <div class="mini-kpi">
                    <span>Pending Invoices</span>
                    <strong><?php echo (int) $summary['pending_invoices']; ?></strong>
                </div>
                <div class="mini-kpi">
                    <span>Paid Registrations</span>
                    <strong><?php echo (int) $summary['paid_registrations']; ?></strong>
                </div>
                <div class="mini-kpi">
                    <span>Average Invoice</span>
                    <strong><?php echo accountingCurrency($summary['average_invoice']); ?></strong>
                </div>
            </div>
            <div class="insight-board">
                <?php foreach ($cfoInsights as $insight): ?>
                    <div class="insight-item">
                        <i class="fas fa-lightbulb"></i>
                        <span><?php echo htmlspecialchars($insight); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Expense Breakdown</div>
                <div class="card-subtitle">Where the business is spending</div>
            </div>
        </div>
        <div style="padding:8px 0;">
            <?php if ($expenseByCategory): ?>
                <?php $expenseTotal = max($summary['total_expenses'], 1); ?>
                <?php foreach ($expenseByCategory as $item): ?>
                    <?php $pct = min(100, round(((float) $item['total'] / $expenseTotal) * 100)); ?>
                    <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);">
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                            <span style="font-weight:600;"><?php echo htmlspecialchars($item['category']); ?></span>
                            <span style="font-weight:700;color:var(--primary);"><?php echo accountingCurrency((float) $item['total']); ?></span>
                        </div>
                        <div style="background:var(--gray-200);border-radius:2px;height:8px;">
                            <div style="background:var(--pma-green);width:<?php echo $pct; ?>%;height:8px;border-radius:2px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:32px 16px;"><i class="fas fa-receipt"></i>No expenses yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;margin-top:24px;">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Recent Registrations</div>
                <div class="card-subtitle">Latest 10 sign-ups</div>
            </div>
            <a href="registrations.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Event</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recent): ?>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></strong></td>
                        <td style="color:#3d3d3d;"><?php echo htmlspecialchars($r['email']); ?></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($r['event_name']); ?>">
                            <?php echo htmlspecialchars($r['event_name']); ?>
                        </td>
                        <td style="color:#6b6b6b;white-space:nowrap;"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state"><i class="fas fa-inbox"></i>No registrations yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Collections Pipeline</div>
                <div class="card-subtitle">What finance should follow up on now</div>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($paymentPipeline): ?>
                    <?php foreach ($paymentPipeline as $invoice): ?>
                        <?php $outstanding = max(0, (float) $invoice['total_amount'] - (float) $invoice['amount_paid']); ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></strong><br>
                                <span style="color:#6b6b6b;font-size:12px;"><?php echo htmlspecialchars($invoice['organization'] ?: '-'); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $invoice['payment_status'] === 'paid' ? 'badge-green' : ($invoice['payment_status'] === 'partial' ? 'badge-orange' : 'badge-red'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($invoice['payment_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo accountingCurrency($outstanding); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="empty-state"><i class="fas fa-money-bill-wave"></i>No invoices yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;align-items:start;margin-top:24px;">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Monthly Performance</div>
                <div class="card-subtitle">Revenue and collections trend</div>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Revenue</th>
                        <th>Collected</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($monthlyPerformance): ?>
                    <?php foreach ($monthlyPerformance as $month): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($month['month_label']); ?></td>
                            <td><?php echo accountingCurrency((float) $month['revenue']); ?></td>
                            <td><?php echo accountingCurrency((float) $month['collected']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="empty-state"><i class="fas fa-chart-column"></i>No monthly data</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">By Event</div>
                <div class="card-subtitle">Registration breakdown</div>
            </div>
        </div>
        <div style="padding:8px 0;">
        <?php if ($eventBreakdown): ?>
            <?php foreach ($eventBreakdown as $eb): ?>
            <?php $pct = $totalRegs > 0 ? round($eb['total'] / $totalRegs * 100) : 0; ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--gray-800);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($eb['event_name']); ?>">
                        <?php echo htmlspecialchars($eb['event_name']); ?>
                    </span>
                    <span style="font-size:12.5px;font-weight:700;color:var(--primary);"><?php echo $eb['total']; ?></span>
                </div>
                <div style="background:var(--gray-200);border-radius:2px;height:6px;">
                    <div style="background:var(--primary);width:<?php echo $pct; ?>%;height:6px;border-radius:2px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:32px 16px;"><i class="fas fa-chart-bar"></i>No data yet</div>
        <?php endif; ?>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Event Profitability</div>
                <div class="card-subtitle">Top event contribution view</div>
            </div>
        </div>
        <div style="padding:8px 0;">
            <?php if ($eventProfitability): ?>
                <?php foreach ($eventProfitability as $eventRow): ?>
                    <?php $contribution = (float) $eventRow['revenue'] - (float) $eventRow['expenses']; ?>
                    <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);">
                        <div style="font-weight:700;color:var(--gray-800);"><?php echo htmlspecialchars($eventRow['title']); ?></div>
                        <div style="font-size:12px;color:#6b6b6b;margin:2px 0 8px;"><?php echo htmlspecialchars($eventRow['location'] . ' | ' . $eventRow['date_display']); ?></div>
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px;">
                            <span>Revenue</span>
                            <strong><?php echo accountingCurrency((float) $eventRow['revenue']); ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px;">
                            <span>Expenses</span>
                            <strong><?php echo accountingCurrency((float) $eventRow['expenses']); ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;">
                            <span>Contribution</span>
                            <strong class="<?php echo $contribution >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo accountingCurrency($contribution); ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:32px 16px;"><i class="fas fa-briefcase"></i>No profitability data yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
