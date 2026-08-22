<?php
/**
 * Registration funnel analytics.
 *
 * Two halves:
 *   1. The funnel itself, from funnel_events (see includes/funnel.php).
 *   2. Panels over event_registrations, which needed no new tracking — the data
 *      was always there, nobody had a page that put it next to the funnel.
 *
 * No charting library. The funnel is four bars and the existing admin panels
 * already draw bars with a div and a width percentage (see the Expense
 * Breakdown block in dashboard.php), so a CDN dependency on an admin page
 * would buy nothing and add a way for this page to render broken offline.
 */

require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/invoice.php';
require_once '../includes/accounting.php';
requireAdminAuth();

// There is no 'analytics' entry in getPermissionFeatures() and adding one would
// silently lock out every existing editor until someone re-granted it. This is
// a reporting view, so it reuses the dashboard permission — and hides the money
// columns from anyone without accounting access, rather than showing an editor
// revenue they are not allowed to see in accounting.php.
requirePermission('dashboard', 'view');
$canSeeMoney = hasPermission('accounting', 'view');

ensureRegistrationInvoiceSchema($pdo);
ensureAccountingSchema($pdo);
ensureFunnelEventsSchema($pdo);

$pageTitle  = 'Analytics';
$activePage = 'analytics';

// ── Filters ─────────────────────────────────────────────────────────────────
$range   = (string) ($_GET['range'] ?? '30');
$from    = trim((string) ($_GET['from'] ?? ''));
$to      = trim((string) ($_GET['to'] ?? ''));
$eventId = (int) ($_GET['event_id'] ?? 0);

$validDate = static fn(string $value): bool =>
    (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;

if (!in_array($range, ['7', '30', 'all', 'custom'], true)) {
    $range = '30';
}

if ($range === 'custom' && (!$validDate($from) || !$validDate($to))) {
    // Incomplete custom range: fall back rather than showing an empty page.
    $range = '30';
}

if ($from > $to && $range === 'custom') {
    [$from, $to] = [$to, $from];
}

/**
 * Build the date + event predicate, optionally prefixed with a table alias.
 *
 * The date arithmetic is done by MySQL (CURDATE(), DATE_SUB) rather than by PHP
 * on purpose: PHP and MySQL can disagree about "today" when they are in
 * different timezones, which is true of the local Docker stack and could become
 * true of production. One clock decides.
 *
 * Both filtered tables (funnel_events, event_registrations) have created_at and
 * event_id columns with the same meaning, so one builder serves every query on
 * this page and the filter cannot drift between panels.
 *
 * @return array{0:string,1:array} predicate fragment, bound parameters
 */
function analyticsScope(string $range, string $from, string $to, int $eventId, string $alias = ''): array
{
    $col = $alias === '' ? '' : $alias . '.';
    $sql = '';
    $params = [];

    switch ($range) {
        case '7':
            $sql .= " AND {$col}created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
            break;
        case '30':
            $sql .= " AND {$col}created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
            break;
        case 'custom':
            $sql .= " AND {$col}created_at >= ? AND {$col}created_at < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $from;
            $params[] = $to;
            break;
        case 'all':
        default:
            break;
    }

    if ($eventId > 0) {
        $sql .= " AND {$col}event_id = ?";
        $params[] = $eventId;
    }

    return [$sql, $params];
}

[$scopeSql, $scopeParams]   = analyticsScope($range, $from, $to, $eventId);
[$scopeSqlR, $scopeParamsR] = analyticsScope($range, $from, $to, $eventId, 'r');

$rangeLabels = [
    '7'      => 'Last 7 days',
    '30'     => 'Last 30 days',
    'all'    => 'All time',
    'custom' => $range === 'custom' ? ($from . ' to ' . $to) : 'Custom range',
];
$rangeLabel = $rangeLabels[$range];

$events = $pdo->query(
    "SELECT id, title, location, date_display, is_active FROM events ORDER BY event_start_date, id"
)->fetchAll();

$selectedEvent = null;
foreach ($events as $eventRow) {
    if ((int) $eventRow['id'] === $eventId) {
        $selectedEvent = $eventRow;
    }
}

// ── The funnel ──────────────────────────────────────────────────────────────
$funnelRaw = [];
$stmt = $pdo->prepare(
    "SELECT event_type, COUNT(*) AS hits, COUNT(DISTINCT session_id) AS sessions
       FROM funnel_events
      WHERE 1=1" . $scopeSql . "
      GROUP BY event_type"
);
$stmt->execute($scopeParams);
foreach ($stmt->fetchAll() as $row) {
    $funnelRaw[$row['event_type']] = [
        'hits'     => (int) $row['hits'],
        'sessions' => (int) $row['sessions'],
    ];
}

$stageOf = static fn(string $type, string $key): int => $funnelRaw[$type][$key] ?? 0;

$stages = [
    ['key' => 'page_view',      'label' => 'Page Views',   'hint' => 'Registration page opened'],
    ['key' => 'form_started',   'label' => 'Form Started', 'hint' => 'Visitor typed in the form'],
    ['key' => 'submit_attempt', 'label' => 'Submitted',    'hint' => 'Form posted to the server'],
    ['key' => 'submit_success', 'label' => 'Succeeded',    'hint' => 'Saved and invoiced'],
];

foreach ($stages as $index => $stage) {
    $stages[$index]['hits']     = $stageOf($stage['key'], 'hits');
    $stages[$index]['sessions'] = $stageOf($stage['key'], 'sessions');
}

$topOfFunnel = max(1, $stages[0]['hits']);
foreach ($stages as $index => $stage) {
    $previous = $index === 0 ? null : $stages[$index - 1]['hits'];

    $stages[$index]['width'] = round(($stage['hits'] / $topOfFunnel) * 100, 1);
    $stages[$index]['drop']  = ($previous !== null && $previous > 0)
        ? round((($previous - $stage['hits']) / $previous) * 100, 1)
        : null;
}

$failedSubmits  = $stageOf('submit_fail', 'hits');
$overallRate    = $stages[0]['hits'] > 0
    ? round(($stages[3]['hits'] / $stages[0]['hits']) * 100, 1)
    : 0.0;
$funnelRowTotal = array_sum(array_column($funnelRaw, 'hits'));

// ── Panels over data that already existed ───────────────────────────────────
$regStmt = $pdo->prepare(
    "SELECT COUNT(*) AS registrations,
            COALESCE(SUM(attendee_count), 0) AS delegates,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(SUM(amount_paid), 0) AS collected
       FROM event_registrations
      WHERE 1=1" . $scopeSql
);
$regStmt->execute($scopeParams);
$registrationTotals = $regStmt->fetch() ?: [
    'registrations' => 0, 'delegates' => 0, 'revenue' => 0, 'collected' => 0,
];

// Grouped on the events row, not on the free-text event_name, so the seven
// pre-2026-08 rows with a NULL event_id are reported as "Unattributed" instead
// of silently splitting one event across several spellings.
$breakdownStmt = $pdo->prepare(
    "SELECT r.event_id,
            COALESCE(e.title, 'Unattributed (no event_id)') AS title,
            COUNT(*) AS registrations,
            COALESCE(SUM(r.attendee_count), 0) AS delegates,
            COALESCE(SUM(r.total_amount), 0) AS revenue
       FROM event_registrations r
       LEFT JOIN events e ON e.id = r.event_id
      WHERE 1=1" . $scopeSqlR . "
      GROUP BY r.event_id, title
      ORDER BY registrations DESC, title"
);
$breakdownStmt->execute($scopeParamsR);
$eventBreakdown = $breakdownStmt->fetchAll();

$paymentStmt = $pdo->prepare(
    "SELECT payment_status,
            COUNT(*) AS registrations,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(SUM(amount_paid), 0) AS collected
       FROM event_registrations
      WHERE 1=1" . $scopeSql . "
      GROUP BY payment_status
      ORDER BY registrations DESC"
);
$paymentStmt->execute($scopeParams);
$paymentBreakdown = $paymentStmt->fetchAll();

// Where the traffic came from. This is the only reason the referrer and utm_*
// columns exist, and it is the answer Priority 2 (Google Ads) will need.
$sourceStmt = $pdo->prepare(
    "SELECT COALESCE(NULLIF(utm_source, ''), NULLIF(referrer, ''), 'Direct / unknown') AS source,
            COUNT(*) AS hits,
            COUNT(DISTINCT session_id) AS sessions
       FROM funnel_events
      WHERE event_type = 'page_view'" . $scopeSql . "
      GROUP BY source
      ORDER BY hits DESC
      LIMIT 8"
);
$sourceStmt->execute($scopeParams);
$trafficSources = $sourceStmt->fetchAll();

$pct = static function (float $part, float $whole): string {
    return $whole > 0 ? number_format(($part / $whole) * 100, 1) . '%' : '—';
};

include 'header.php';
?>

<div class="card" style="padding:16px 20px;margin-bottom:20px;">
    <form method="GET" class="filter-bar" action="analytics.php">
        <div>
            <label>Date range</label>
            <select name="range" id="rangeSelect" class="form-control">
                <option value="7"      <?php echo $range === '7'      ? 'selected' : ''; ?>>Last 7 days</option>
                <option value="30"     <?php echo $range === '30'     ? 'selected' : ''; ?>>Last 30 days</option>
                <option value="all"    <?php echo $range === 'all'    ? 'selected' : ''; ?>>All time</option>
                <option value="custom" <?php echo $range === 'custom' ? 'selected' : ''; ?>>Custom range</option>
            </select>
        </div>
        <div id="customFrom" style="<?php echo $range === 'custom' ? '' : 'display:none;'; ?>">
            <label>From</label>
            <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div id="customTo" style="<?php echo $range === 'custom' ? '' : 'display:none;'; ?>">
            <label>To</label>
            <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <div>
            <label>Event</label>
            <select name="event_id" class="form-control">
                <option value="0">All Events</option>
                <?php foreach ($events as $eventRow): ?>
                    <option value="<?php echo (int) $eventRow['id']; ?>" <?php echo $eventId === (int) $eventRow['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($eventRow['title']); ?>
                        <?php echo ((int) $eventRow['is_active'] === 1) ? '' : ' (inactive)'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="align-self:flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
            <a href="analytics.php" class="btn btn-outline">Reset</a>
        </div>
    </form>
</div>

<?php if ($funnelRowTotal === 0): ?>
<div class="alert alert-danger" style="margin-bottom:20px;">
    <i class="fas fa-circle-info"></i>
    No funnel events recorded for this filter yet. Tracking starts the first time
    someone opens a registration page after this feature is deployed — historical
    registrations have no funnel data, by definition.
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-eye"></i></div>
        <div class="stat-info">
            <h3 data-metric="page_views"><?php echo $stages[0]['hits']; ?></h3>
            <p>Registration Page Views</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-percent"></i></div>
        <div class="stat-info">
            <h3 data-metric="conversion_rate"><?php echo number_format($overallRate, 1); ?>%</h3>
            <p>View → Registration Rate</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3 data-metric="registrations"><?php echo (int) $registrationTotals['registrations']; ?></h3>
            <p>Registrations (<?php echo htmlspecialchars($rangeLabel); ?>)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="stat-info">
            <h3 data-metric="failed_submits"><?php echo $failedSubmits; ?></h3>
            <p>Failed Submissions</p>
        </div>
    </div>
    <?php if ($canSeeMoney): ?>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div>
        <div class="stat-info">
            <h3 data-metric="revenue"><?php echo accountingCurrency((float) $registrationTotals['revenue']); ?></h3>
            <p>Invoiced Revenue</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <h3 data-metric="collected"><?php echo accountingCurrency((float) $registrationTotals['collected']); ?></h3>
            <p>Cash Collected</p>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-group"></i></div>
        <div class="stat-info">
            <h3 data-metric="delegates"><?php echo (int) $registrationTotals['delegates']; ?></h3>
            <p>Delegates Booked</p>
        </div>
    </div>
</div>

<div class="table-card" style="margin-top:24px;">
    <div class="table-card-header">
        <div>
            <div class="card-title">Registration Funnel</div>
            <div class="card-subtitle">
                <?php echo htmlspecialchars($rangeLabel); ?>
                &middot;
                <?php echo $selectedEvent ? htmlspecialchars($selectedEvent['title']) : 'All events'; ?>
            </div>
        </div>
    </div>
    <div style="padding:20px;">
        <?php foreach ($stages as $index => $stage): ?>
            <?php if ($index > 0): ?>
                <div style="display:flex;align-items:center;gap:8px;margin:0 0 10px 2px;font-size:12px;color:#94a3b8;">
                    <i class="fas fa-arrow-down"></i>
                    <?php // One line, and always rendered: local-dev/verify.sh asserts against it. ?>
                    <span data-dropoff="<?php echo htmlspecialchars($stage['key']); ?>" data-drop="<?php echo $stage['drop'] === null ? '' : number_format($stage['drop'], 1); ?>"><?php echo $stage['drop'] === null ? 'no drop-off to measure' : number_format($stage['drop'], 1) . '% drop-off'; ?></span>
                </div>
            <?php endif; ?>
            <?php // Attributes kept on one line: local-dev/verify.sh asserts against them. ?>
            <div data-funnel-stage="<?php echo htmlspecialchars($stage['key']); ?>" data-count="<?php echo $stage['hits']; ?>" data-sessions="<?php echo $stage['sessions']; ?>" style="margin-bottom:<?php echo $index === count($stages) - 1 ? '0' : '14px'; ?>;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;gap:12px;">
                    <span style="font-weight:600;color:var(--gray-800);">
                        <?php echo htmlspecialchars($stage['label']); ?>
                        <span style="font-weight:400;color:#94a3b8;font-size:12px;">
                            &middot; <?php echo htmlspecialchars($stage['hint']); ?>
                        </span>
                    </span>
                    <span style="white-space:nowrap;">
                        <strong style="color:var(--primary);font-size:15px;"><?php echo $stage['hits']; ?></strong>
                        <span style="color:#94a3b8;font-size:12px;">
                            (<?php echo $stage['sessions']; ?> session<?php echo $stage['sessions'] === 1 ? '' : 's'; ?>,
                            <?php echo $pct((float) $stage['hits'], (float) $stages[0]['hits']); ?> of views)
                        </span>
                    </span>
                </div>
                <div style="background:var(--gray-200);border-radius:20px;height:22px;overflow:hidden;">
                    <div style="background:linear-gradient(90deg,#00B140,#0f766e);width:<?php echo $stage['width']; ?>%;height:22px;border-radius:20px;min-width:<?php echo $stage['hits'] > 0 ? '2px' : '0'; ?>;"></div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($failedSubmits > 0): ?>
            <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--gray-200);font-size:13px;color:#b45309;">
                <i class="fas fa-triangle-exclamation"></i>
                <?php echo $failedSubmits; ?> submission<?php echo $failedSubmits === 1 ? '' : 's'; ?>
                failed validation or the database write in this range. Email that
                could not be delivered is <em>not</em> counted here — see
                <code>failed_notifications</code>.
            </div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start;margin-top:24px;">
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Per Event</div>
                <div class="card-subtitle">Registrations<?php echo $canSeeMoney ? ' and revenue' : ''; ?> in range</div>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Registrations</th>
                        <th>Delegates</th>
                        <?php if ($canSeeMoney): ?><th>Revenue</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($eventBreakdown): ?>
                    <?php foreach ($eventBreakdown as $row): ?>
                        <tr data-event-row="<?php echo (int) ($row['event_id'] ?? 0); ?>">
                            <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                            <td data-cell="registrations"><?php echo (int) $row['registrations']; ?></td>
                            <td data-cell="delegates"><?php echo (int) $row['delegates']; ?></td>
                            <?php if ($canSeeMoney): ?>
                            <td data-cell="revenue"><?php echo accountingCurrency((float) $row['revenue']); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo $canSeeMoney ? 4 : 3; ?>" class="empty-state"><i class="fas fa-inbox"></i>No registrations in this range</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <div>
                <div class="card-title">Payment Status</div>
                <div class="card-subtitle">Registrations in range by status</div>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <?php if ($canSeeMoney): ?><th>Invoiced</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($paymentBreakdown): ?>
                    <?php foreach ($paymentBreakdown as $row): ?>
                        <?php $status = (string) $row['payment_status']; ?>
                        <tr data-payment-row="<?php echo htmlspecialchars($status); ?>">
                            <td>
                                <span class="badge <?php echo $status === 'paid' ? 'badge-green' : ($status === 'partial' ? 'badge-orange' : 'badge-red'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td data-cell="count"><?php echo (int) $row['registrations']; ?></td>
                            <?php if ($canSeeMoney): ?>
                            <td data-cell="revenue"><?php echo accountingCurrency((float) $row['revenue']); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo $canSeeMoney ? 3 : 2; ?>" class="empty-state"><i class="fas fa-money-bill-wave"></i>No registrations in this range</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="table-card" style="margin-top:24px;">
    <div class="table-card-header">
        <div>
            <div class="card-title">Where Page Views Came From</div>
            <div class="card-subtitle">utm_source when present, otherwise the referring site</div>
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Page Views</th>
                    <th>Sessions</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($trafficSources): ?>
                <?php foreach ($trafficSources as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['source']); ?></td>
                        <td><?php echo (int) $row['hits']; ?></td>
                        <td><?php echo (int) $row['sessions']; ?></td>
                        <td><?php echo $pct((float) $row['hits'], (float) $stages[0]['hits']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="empty-state"><i class="fas fa-signs-post"></i>No page views recorded in this range</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p style="margin-top:20px;font-size:12px;color:#94a3b8;line-height:1.7;">
    Funnel figures come from <code>funnel_events</code>, which holds no IP
    address, no user agent and nothing that identifies a person &mdash; sessions
    are correlated by one first-party 24-hour cookie carrying a random UUID.
    Registration, delegate and revenue figures come from
    <code>event_registrations</code> and predate this page.
</p>

<script>
    // Show the two date inputs only when "Custom range" is selected.
    (function () {
        var select = document.getElementById('rangeSelect');
        var from = document.getElementById('customFrom');
        var to = document.getElementById('customTo');
        if (!select || !from || !to) { return; }

        select.addEventListener('change', function () {
            var show = select.value === 'custom' ? '' : 'none';
            from.style.display = show;
            to.style.display = show;
        });
    })();
</script>

<?php include 'footer.php'; ?>
