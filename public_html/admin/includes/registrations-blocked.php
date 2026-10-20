<?php
/**
 * The Blocked tab of the Registrations screen: what the form guard stopped and
 * why, kept for 45 days, so a person who was wrongly stopped can be found and
 * answered. Included by registrations.php, which has already authenticated the
 * visitor.
 *
 * @var PDO $pdo
 */

require_once __DIR__ . '/../../includes/formguard-safe.php';
require_once __DIR__ . '/../../includes/csv.php';
require_once __DIR__ . '/../../includes/audit.php';

if (function_exists('pmGuardEnsureSchema')) {
    pmGuardEnsureSchema($pdo);
}

const PM_BLOCKED_REASONS = [
    'honeypot'     => 'A hidden field that people cannot see was filled in',
    'random-text'  => 'Random-looking text in two or more fields',
    'link-in-name' => 'A web address typed into a name field',
    'no-stamp'     => 'The form had no valid timestamp (it expired, or was not sent from the form)',
    'too-fast'     => 'Sent in a few seconds, faster than a person can fill it in',
    'rate'         => 'Too many attempts from one connection',
    'repeat'       => 'The same registration sent again within minutes',
];

const PM_BLOCKED_FORMS = [
    'event' => 'Event registration', 'webinar' => 'Webinar', 'sponsorship' => 'Sponsorship enquiry',
    'newsletter' => 'Newsletter', 'contact' => 'Contact',
];

$bForm     = trim((string) ($_GET['form'] ?? ''));
$bSeverity = trim((string) ($_GET['severity'] ?? ''));

$bWhere  = '1=1';
$bParams = [];
if (isset(PM_BLOCKED_FORMS[$bForm])) {
    $bWhere .= ' AND form = ?';
    $bParams[] = $bForm;
} else {
    $bForm = '';
}
if (in_array($bSeverity, ['bot', 'soft'], true)) {
    $bWhere .= ' AND severity = ?';
    $bParams[] = $bSeverity;
} else {
    $bSeverity = '';
}

$bRows = static function (int $limit, int $offset = 0) use ($pdo, $bWhere, $bParams): array {
    try {
        $stmt = $pdo->prepare('SELECT * FROM form_quarantine WHERE ' . $bWhere . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($bParams);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('blocked: list failed: ' . $e->getMessage());

        return [];
    }
};

$bLabelReasons = static function (string $reasons): string {
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', $reasons))) as $r) {
        $out[] = PM_BLOCKED_REASONS[$r] ?? $r;
    }

    return implode('; ', $out);
};

if (($_GET['export'] ?? '') === 'csv') {
    $bExport = static function () use ($bRows, $bLabelReasons): iterable {
        foreach ($bRows(5000) as $r) {
            $s = json_decode((string) $r['summary'], true) ?: [];
            yield [
                $r['id'], $r['created_at'], PM_BLOCKED_FORMS[$r['form']] ?? $r['form'],
                $r['severity'] === 'bot' ? 'Bot' : 'Stopped, person told why',
                $bLabelReasons((string) $r['reasons']),
                $s['email'] ?? '',
                trim(($s['name'] ?? '') . ' ' . ($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')),
                $s['organization'] ?? ($s['organisation'] ?? ''),
                $s['country'] ?? '',
            ];
        }
    };

    pmCsvSend('blocked_submissions', [
        'ID', 'When', 'Form', 'Verdict', 'Why', 'Email', 'Name', 'Institution', 'Country',
    ], $bExport());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_blocked'])) {
    if (!hasPermission('registrations', 'delete')) {
        $_SESSION['perm_error'] = "You don't have permission to delete registrations.";
    } elseif (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $gone = (int) $pdo->exec('DELETE FROM form_quarantine');
        pmAudit($pdo, 'blocked_purge', 'Cleared ' . $gone . ' blocked submission(s)');
    }

    header('Location: registrations.php?tab=blocked');
    exit;
}

$bTotal = 0;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM form_quarantine WHERE ' . $bWhere);
    $stmt->execute($bParams);
    $bTotal = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('blocked: count failed: ' . $e->getMessage());
}

$bPerPage = 50;
$bPages   = max(1, (int) ceil($bTotal / $bPerPage));
$bPage    = min(max(1, (int) ($_GET['page'] ?? 1)), $bPages);
$bList    = $bRows($bPerPage, ($bPage - 1) * $bPerPage);

$bQuery = static function (array $extra = []) use ($bForm, $bSeverity): string {
    return http_build_query(array_filter(array_merge(
        ['tab' => 'blocked', 'form' => $bForm, 'severity' => $bSeverity], $extra
    ), static fn ($v) => $v !== '' && $v !== null));
};

$csrfToken = generateCsrfToken();

include 'header.php';
pmRegistrationTabs('blocked', $regTabCounts);
?>

<div class="card" style="padding:16px 20px;margin-bottom:20px;">
    <p style="margin:0 0 14px;color:#555;font-size:13.5px;max-width:78ch;">
        These submissions were stopped by the bot protection and nothing was saved or emailed for them.
        They are kept for 45 days. <strong>Bot</strong> means it looked automated and the sender was told it worked;
        <strong>Stopped, person told why</strong> means the sender was shown what to do (reload, wait, or that we already have it).
        If you recognise a real person here, contact them using the email shown.
    </p>
    <form method="GET" action="registrations.php" class="pma-toolbar" style="border:0;padding:0">
        <input type="hidden" name="tab" value="blocked">
        <label>
            <span class="pma-vh">Form</span>
            <select name="form">
                <option value="">All forms</option>
<?php foreach (PM_BLOCKED_FORMS as $key => $label): ?>
                <option value="<?php echo $key; ?>"<?php echo $bForm === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
            </select>
        </label>
        <label>
            <span class="pma-vh">Verdict</span>
            <select name="severity">
                <option value="">Any verdict</option>
                <option value="bot"<?php echo $bSeverity === 'bot' ? ' selected' : ''; ?>>Bot</option>
                <option value="soft"<?php echo $bSeverity === 'soft' ? ' selected' : ''; ?>>Stopped, person told why</option>
            </select>
        </label>
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
<?php if ($bForm !== '' || $bSeverity !== ''): ?>
        <a href="registrations.php?tab=blocked" class="btn btn-outline btn-sm">Clear</a>
<?php endif; ?>
        <a href="registrations.php?<?php echo htmlspecialchars($bQuery(['export' => 'csv'])); ?>"
           class="btn btn-outline btn-sm" style="margin-left:auto">Export CSV</a>
<?php if (hasPermission('registrations', 'delete') && $bTotal > 0): ?>
        <button type="submit" form="purge-blocked" class="btn btn-danger btn-sm"
                onclick="return confirm('Clear every blocked submission, not only the ones shown? This cannot be undone.');">Clear all</button>
<?php endif; ?>
    </form>
    <form id="purge-blocked" method="POST" style="display:none">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="purge_blocked" value="1">
    </form>
</div>

<div class="table-card">
    <div class="table-card-header">
        <div class="card-title"><?php echo $bTotal; ?> blocked submission<?php echo $bTotal !== 1 ? 's' : ''; ?></div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Form</th>
                    <th>Verdict</th>
                    <th>Why</th>
                    <th>What was sent</th>
                </tr>
            </thead>
            <tbody>
<?php if ($bList): ?>
<?php   foreach ($bList as $r): ?>
<?php     $s = json_decode((string) $r['summary'], true) ?: []; ?>
                <tr>
                    <td style="white-space:nowrap;color:#6b6b6b;font-size:12.5px;">
                        <?php echo htmlspecialchars(date('M d, Y', strtotime((string) $r['created_at']))); ?><br>
                        <?php echo htmlspecialchars(date('H:i:s', strtotime((string) $r['created_at']))); ?>
                    </td>
                    <td><?php echo htmlspecialchars(PM_BLOCKED_FORMS[$r['form']] ?? (string) $r['form']); ?></td>
                    <td><span class="badge <?php echo $r['severity'] === 'bot' ? 'badge-gray' : 'badge-green'; ?>"><?php
                        echo $r['severity'] === 'bot' ? 'Bot' : 'Person told why'; ?></span></td>
                    <td style="max-width:300px;font-size:13px;"><?php echo htmlspecialchars($bLabelReasons((string) $r['reasons'])); ?></td>
                    <td style="max-width:360px;font-size:12.5px;word-break:break-word;">
                        <strong><?php echo htmlspecialchars((string) ($s['email'] ?? '')); ?></strong><br>
<?php     foreach ($s as $field => $value): ?>
<?php       if ($field !== 'email' && trim((string) $value) !== ''): ?>
                        <span style="color:#6b6b6b;"><?php echo htmlspecialchars((string) $field); ?>:</span>
                        <?php echo htmlspecialchars(mb_substr((string) $value, 0, 80)); ?><br>
<?php       endif; ?>
<?php     endforeach; ?>
                    </td>
                </tr>
<?php   endforeach; ?>
<?php else: ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <span class="empty-state-title">Nothing has been blocked</span>
                            Either the forms are quiet, or nobody has tried to get past them.
                        </div>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
<?php if ($bPages > 1): ?>
    <div style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:6px;justify-content:center;border-top:1px solid var(--gray-200);">
<?php   for ($i = 1; $i <= $bPages; $i++): ?>
        <a href="registrations.php?<?php echo htmlspecialchars($bQuery(['page' => (string) $i])); ?>"
           class="btn <?php echo $i === $bPage ? 'btn-primary' : 'btn-outline'; ?> btn-sm"
           style="min-width:36px;justify-content:center;"><?php echo $i; ?></a>
<?php   endfor; ?>
    </div>
<?php endif; ?>
</div>

<?php include 'footer.php'; ?>
