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
require_once __DIR__ . '/../../includes/guard-release.php';
require_once __DIR__ . '/../../includes/webinars.php';

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
    'low-score'    => 'Google reCAPTCHA scored the visit as unlikely to be a person',
    'recaptcha-failed' => 'The reCAPTCHA check was expired, already used, or from another form',
    'no-token'     => 'No reCAPTCHA check came with the form (it was not sent from our page)',
];

const PM_BLOCKED_STATUSES = [
    'blocked'   => 'Waiting',
    'released'  => 'Let through',
    'invited'   => 'Invited to register',
    'dismissed' => 'Not a person',
];

const PM_BLOCKED_FORMS = [
    'event' => 'Event registration', 'webinar' => 'Webinar', 'sponsorship' => 'Sponsorship enquiry',
    'newsletter' => 'Newsletter', 'contact' => 'Contact',
];

$bForm     = trim((string) ($_GET['form'] ?? ''));
$bSeverity = trim((string) ($_GET['severity'] ?? ''));
$bStatus   = (string) ($_GET['status'] ?? 'blocked');

if ($bStatus !== 'all' && !isset(PM_BLOCKED_STATUSES[$bStatus])) {
    $bStatus = 'blocked';
}

$bWhere  = '1=1';
$bParams = [];
if ($bStatus !== 'all') {
    $bWhere .= ' AND status = ?';
    $bParams[] = $bStatus;
}
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
                PM_BLOCKED_STATUSES[$r['status']] ?? $r['status'],
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
        'ID', 'When', 'Form', 'Status', 'Verdict', 'Why', 'Email', 'Name', 'Institution', 'Country',
    ], $bExport());
}

$bCanRelease = hasPermission('registrations', 'release') || hasPermission('registrations', 'delete');
$bBy         = (string) ($_SESSION['admin_username'] ?? 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blocked_action'])) {
    $flash = ['ok' => false, 'text' => 'That did not go through.'];

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $flash['text'] = 'That form had expired. Please try again.';
    } elseif (!$bCanRelease) {
        $flash['text'] = "You don't have permission to let blocked people through.";
    } else {
        $bId     = (int) ($_POST['id'] ?? 0);
        $bAction = (string) $_POST['blocked_action'];

        if ($bAction === 'release') {
            $r = pmGuardRelease($pdo, $bId, $bBy, ['session_id' => (int) ($_POST['session_id'] ?? 0)]);
            $flash = ['ok' => $r['ok'], 'text' => $r['message'], 'link' => $r['link']];
        } elseif ($bAction === 'invite') {
            $r = pmGuardInvite($pdo, $bId, (int) ($_POST['event_id'] ?? 0), $bBy);
            $flash = ['ok' => $r['ok'], 'text' => $r['message']];
        } elseif ($bAction === 'dismiss') {
            $done  = pmGuardDismiss($pdo, $bId, $bBy);
            $flash = ['ok' => $done, 'text' => $done ? 'Marked as not a person.' : 'That one has already been dealt with.'];
        } elseif ($bAction === 'dismiss_shown') {
            $stmt = $pdo->prepare("UPDATE form_quarantine SET status = 'dismissed', resolved_at = NOW(), resolved_by = ?, resolution = 'Confirmed as a bot' WHERE " . $bWhere . " AND status = 'blocked'");
            $stmt->execute(array_merge([$bBy], $bParams));
            $n = $stmt->rowCount();
            pmAudit($pdo, 'blocked_dismiss_many', 'Marked ' . $n . ' blocked submission(s) as not a person');
            $flash = ['ok' => true, 'text' => 'Marked ' . $n . ' as not a person.'];
        }
    }

    $_SESSION['blocked_flash'] = $flash;
    header('Location: registrations.php?' . http_build_query(array_filter(['tab' => 'blocked', 'form' => $bForm, 'severity' => $bSeverity, 'status' => $bStatus === 'blocked' ? '' : $bStatus])));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_blocked'])) {
    if (!hasPermission('registrations', 'delete')) {
        $_SESSION['perm_error'] = "You don't have permission to delete registrations.";
    } elseif (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        // The record of who was let through or invited is kept until it expires.
        $gone = (int) $pdo->exec("DELETE FROM form_quarantine WHERE status IN ('blocked', 'dismissed')");
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
        ['tab' => 'blocked', 'form' => $bForm, 'severity' => $bSeverity, 'status' => $bStatus === 'blocked' ? '' : $bStatus], $extra
    ), static fn ($v) => $v !== '' && $v !== null));
};

$csrfToken = generateCsrfToken();

$bFlash = $_SESSION['blocked_flash'] ?? null;
unset($_SESSION['blocked_flash']);

$bSessions = pmWebinarSessions($pdo, false);
$bEvents   = [];
try {
    $bEvents = $pdo->query('SELECT id, title, date_display FROM events WHERE is_active = 1 ORDER BY sort_order, event_start_date, id')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('blocked: events list failed: ' . $e->getMessage());
}

include 'header.php';
pmRegistrationTabs('blocked', $regTabCounts);
?>

<?php if (is_array($bFlash)): ?>
<div class="alert <?php echo !empty($bFlash['ok']) ? 'alert-success' : 'alert-danger'; ?>">
    <?php echo htmlspecialchars((string) ($bFlash['text'] ?? '')); ?>
<?php   if (!empty($bFlash['link'])): ?>
    <a href="<?php echo htmlspecialchars((string) $bFlash['link']); ?>" style="margin-left:8px;text-decoration:underline;">See them on the list</a>
<?php   endif; ?>
</div>
<?php endif; ?>

<div class="card" style="padding:16px 20px;margin-bottom:20px;">
    <p style="margin:0 0 14px;color:#555;font-size:13.5px;max-width:84ch;">
        These submissions were stopped by the bot protection, and nothing was saved or emailed for them.
        They are kept for 45 days. If one is a real person, press <strong>Let through</strong>: they are registered exactly as
        if they had never been stopped, with the same invoice and emails, and appear on the registration list.
        A row that shows <strong>Invite to register</strong> was stopped before the whole form was kept, so it holds too
        little to register them. Choose the school and they are emailed a link to register themselves.
    </p>
    <form method="GET" action="registrations.php" class="pma-toolbar" style="border:0;padding:0">
        <input type="hidden" name="tab" value="blocked">
        <label>
            <span class="pma-vh">Status</span>
            <select name="status">
                <option value="blocked"<?php echo $bStatus === 'blocked' ? ' selected' : ''; ?>>Waiting</option>
<?php foreach (PM_BLOCKED_STATUSES as $key => $label): ?>
<?php   if ($key !== 'blocked'): ?>
                <option value="<?php echo $key; ?>"<?php echo $bStatus === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
<?php   endif; ?>
<?php endforeach; ?>
                <option value="all"<?php echo $bStatus === 'all' ? ' selected' : ''; ?>>All</option>
            </select>
        </label>
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
<?php if ($bForm !== '' || $bSeverity !== '' || $bStatus !== 'blocked'): ?>
        <a href="registrations.php?tab=blocked" class="btn btn-outline btn-sm">Clear</a>
<?php endif; ?>
        <a href="registrations.php?<?php echo htmlspecialchars($bQuery(['export' => 'csv'])); ?>"
           class="btn btn-outline btn-sm" style="margin-left:auto">Export CSV</a>
<?php if ($bCanRelease && $bStatus === 'blocked' && $bTotal > 0): ?>
        <button type="submit" form="dismiss-shown" class="btn btn-outline btn-sm"
                onclick="return confirm('Mark all <?php echo (int) $bTotal; ?> shown as not a person? They leave this list. Real people among them will not be registered.');">Not a person: all <?php echo (int) $bTotal; ?> shown</button>
<?php endif; ?>
<?php if (hasPermission('registrations', 'delete') && $bTotal > 0): ?>
        <button type="submit" form="purge-blocked" class="btn btn-danger btn-sm"
                onclick="return confirm('Delete every submission still waiting or marked not a person, not only the ones shown? Those still waiting cannot be let through afterwards. This cannot be undone.');">Delete waiting</button>
<?php endif; ?>
    </form>
    <form id="purge-blocked" method="POST" style="display:none">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="purge_blocked" value="1">
    </form>
    <form id="dismiss-shown" method="POST" style="display:none">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="blocked_action" value="dismiss_shown">
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
                    <th>What to do</th>
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
<?php     $bExtra = $r['payload'] !== null ? (json_decode((string) $r['payload'], true) ?: []) : []; ?>
<?php     if ($bExtra !== []): ?>
                        <span style="color:#6b6b6b;">kept the whole form:</span>
<?php       foreach (['phone' => 'phone', 'tier' => 'tier', 'session_id' => 'session id', 'event_id' => 'event id'] as $k => $label): ?>
<?php         if (isset($bExtra[$k]) && $bExtra[$k] !== ''): ?>
                        <?php echo htmlspecialchars($label); ?> <?php echo htmlspecialchars((string) $bExtra[$k]); ?>;
<?php         endif; ?>
<?php       endforeach; ?>
<?php       if (!empty($bExtra['attendees']['first_name']) && is_array($bExtra['attendees']['first_name'])): ?>
                        <?php echo count(array_filter($bExtra['attendees']['first_name'], static fn ($v) => trim((string) $v) !== '')); ?> delegate(s)<br>
<?php       else: ?>
                        <br>
<?php       endif; ?>
<?php     endif; ?>
<?php     foreach ($s as $field => $value): ?>
<?php       if ($field !== 'email' && trim((string) $value) !== ''): ?>
                        <span style="color:#6b6b6b;"><?php echo htmlspecialchars((string) $field); ?>:</span>
                        <?php echo htmlspecialchars(mb_substr((string) $value, 0, 80)); ?><br>
<?php       endif; ?>
<?php     endforeach; ?>
                    </td>
                    <td style="min-width:230px;font-size:13px;">
<?php     $bPay = $r['payload'] !== null ? (json_decode((string) $r['payload'], true) ?: []) : []; ?>
<?php     if ($r['status'] === 'blocked'): ?>
<?php       if ($bCanRelease): ?>
<?php         $bWhole = $bPay !== []; ?>
<?php         if ($bWhole || in_array($r['form'], ['webinar', 'sponsorship', 'newsletter', 'contact'], true)): ?>
                        <form method="POST" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:6px;"
                              onsubmit="return confirm('Register <?php echo htmlspecialchars(addslashes((string) ($s['email'] ?? ''))); ?> exactly as if they had never been stopped?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="blocked_action" value="release">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
<?php           if ($r['form'] === 'webinar' && !$bWhole): ?>
                            <select name="session_id" aria-label="Session" style="max-width:190px;">
                                <option value="0">Whole series</option>
<?php             foreach ($bSessions as $bSession): ?>
                                <option value="<?php echo (int) $bSession['id']; ?>">Session <?php echo (int) $bSession['session_number']; ?>: <?php echo htmlspecialchars(mb_substr((string) $bSession['title'], 0, 28)); ?></option>
<?php             endforeach; ?>
                            </select>
<?php           endif; ?>
                            <button type="submit" class="btn btn-primary btn-sm">Let through</button>
                        </form>
<?php         elseif ($r['form'] === 'event'): ?>
                        <form method="POST" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:6px;"
                              onsubmit="return confirm('Email <?php echo htmlspecialchars(addslashes((string) ($s['email'] ?? ''))); ?> a link to register?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="blocked_action" value="invite">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <select name="event_id" aria-label="School" style="max-width:200px;" required>
                                <option value="">Which school?</option>
<?php             foreach ($bEvents as $bEvent): ?>
                                <option value="<?php echo (int) $bEvent['id']; ?>"><?php echo htmlspecialchars(mb_substr((string) $bEvent['title'], 0, 34)); ?></option>
<?php             endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Invite to register</button>
                        </form>
<?php         endif; ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="blocked_action" value="dismiss">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm">Not a person</button>
                        </form>
<?php       else: ?>
                        <span style="color:#6b6b6b;">You can look, but not release.</span>
<?php       endif; ?>
<?php     else: ?>
                        <span class="badge <?php echo $r['status'] === 'dismissed' ? 'badge-gray' : 'badge-green'; ?>"><?php echo htmlspecialchars(PM_BLOCKED_STATUSES[$r['status']] ?? (string) $r['status']); ?></span><br>
                        <span style="color:#6b6b6b;"><?php echo htmlspecialchars((string) ($r['resolution'] ?? '')); ?></span><br>
                        <span style="color:#6b6b6b;font-size:12px;"><?php echo htmlspecialchars((string) ($r['resolved_by'] ?? '')); ?>, <?php echo htmlspecialchars($r['resolved_at'] ? date('M d, H:i', strtotime((string) $r['resolved_at'])) : ''); ?></span>
<?php       if ($r['status'] === 'released'): ?>
<?php         $bTo = $r['form'] === 'webinar' ? 'registrations.php?tab=webinars&search=' : ($r['form'] === 'event' ? 'registrations.php?search=' : ''); ?>
<?php         if ($bTo !== ''): ?>
                        <br><a href="<?php echo htmlspecialchars($bTo . rawurlencode((string) ($s['email'] ?? ''))); ?>" style="text-decoration:underline;">See on the list</a>
<?php         endif; ?>
<?php       endif; ?>
<?php     endif; ?>
                    </td>
                </tr>
<?php   endforeach; ?>
<?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <span class="empty-state-title">Nothing here</span>
                            Nothing matches. Either the forms are quiet, or everything stopped has been dealt with.
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
