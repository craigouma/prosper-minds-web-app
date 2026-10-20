<?php
/**
 * The Webinars tab of the Registrations screen: every PFM Insight Live
 * sign-up, filterable by session and exportable. Included by
 * registrations.php, which has already authenticated the visitor.
 *
 * @var PDO $pdo
 */

require_once __DIR__ . '/../../includes/webinars.php';
require_once __DIR__ . '/../../includes/csv.php';
require_once __DIR__ . '/../../includes/audit.php';

ensureWebinarSchema($pdo);

$wSearch  = trim((string) ($_GET['search'] ?? ''));
$wSession = trim((string) ($_GET['session'] ?? 'all'));
$wStatus  = trim((string) ($_GET['status'] ?? 'all'));

$wSessions = pmWebinarSessions($pdo, false);
$wTitles   = [];
foreach ($wSessions as $wRow) {
    $wTitles[(int) $wRow['id']] = $wRow;
}

if ($wSession !== 'all' && $wSession !== 'series' && !isset($wTitles[(int) $wSession])) {
    $wSession = 'all';
}
if (!in_array($wStatus, ['all', 'active', 'unsubscribed'], true)) {
    $wStatus = 'all';
}

[$wWhere, $wParams] = pmWebinarRegistrationFilter($wSearch, $wSession, $wStatus);

if (($_GET['export'] ?? '') === 'csv') {
    $wExport = static function () use ($pdo, $wWhere, $wParams, $wTitles): iterable {
        foreach (pmWebinarRegistrationsFiltered($pdo, $wWhere, $wParams) as $r) {
            $sid = (int) $r['session_id'];
            yield [
                $r['id'],
                $r['created_at'],
                $r['name'],
                $r['email'],
                $r['organization'] ?? '',
                $r['country'] ?? '',
                $sid === 0 ? 'Whole series' : 'Session ' . str_pad((string) ($wTitles[$sid]['session_number'] ?? $sid), 2, '0', STR_PAD_LEFT) . ': ' . ($wTitles[$sid]['title'] ?? ''),
                $sid === 0 ? '' : ($wTitles[$sid]['session_date'] ?? ''),
                $r['unsubscribed_at'] ? 'Unsubscribed' : 'Active',
                $r['unsubscribed_at'] ?? '',
            ];
        }
    };

    pmCsvSend('webinar_registrations', [
        'ID', 'Registered', 'Name', 'Email', 'Institution', 'Country',
        'Registered for', 'Session date', 'Status', 'Unsubscribed on',
    ], $wExport());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_webinar_id'])) {
    if (!hasPermission('registrations', 'delete')) {
        $_SESSION['perm_error'] = "You don't have permission to delete registrations.";
    } elseif (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $wId  = (int) $_POST['delete_webinar_id'];
        $wOld = $pdo->prepare('SELECT name, email FROM webinar_registrations WHERE id = ?');
        $wOld->execute([$wId]);
        $wGone = $wOld->fetch(PDO::FETCH_ASSOC);

        if ($wGone) {
            $pdo->prepare('DELETE FROM webinar_registrations WHERE id = ?')->execute([$wId]);
            pmAudit($pdo, 'webinar_registration_delete', 'Deleted webinar registration #' . $wId . ' (' . $wGone['email'] . ')');
        }
    }

    header('Location: registrations.php?' . http_build_query(array_filter([
        'tab' => 'webinars', 'search' => $wSearch, 'session' => $wSession === 'all' ? '' : $wSession,
        'status' => $wStatus === 'all' ? '' : $wStatus,
    ])));
    exit;
}

$wPerPage = 50;
$wPage    = max(1, (int) ($_GET['page'] ?? 1));
$wTotal   = pmWebinarRegistrationsFilteredCount($pdo, $wWhere, $wParams);
$wPages   = max(1, (int) ceil($wTotal / $wPerPage));
$wPage    = min($wPage, $wPages);
$wRows    = pmWebinarRegistrationsFiltered($pdo, $wWhere, $wParams, $wPerPage, ($wPage - 1) * $wPerPage);

$wQuery = static function (array $extra = []) use ($wSearch, $wSession, $wStatus): string {
    return http_build_query(array_filter(array_merge([
        'tab'     => 'webinars',
        'search'  => $wSearch,
        'session' => $wSession === 'all' ? '' : $wSession,
        'status'  => $wStatus === 'all' ? '' : $wStatus,
    ], $extra), static fn ($v) => $v !== '' && $v !== null));
};

$csrfToken = generateCsrfToken();
$wFiltered = $wSearch !== '' || $wSession !== 'all' || $wStatus !== 'all';

include 'header.php';
pmRegistrationTabs('webinars', $regTabCounts);
?>

<div class="card" style="padding:16px 20px;margin-bottom:20px;">
    <form method="GET" action="registrations.php" class="pma-toolbar" style="border:0;padding:0">
        <input type="hidden" name="tab" value="webinars">
        <label class="pma-search">
            <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M7.2 3a4.2 4.2 0 110 8.4 4.2 4.2 0 010-8.4zM10.4 10.4 13.5 13.5"
                      fill="none" stroke="#6b6b6b" stroke-width="1.3"></path>
            </svg>
            <span class="pma-vh">Search webinar registrations</span>
            <input type="search" name="search" placeholder="Name, email, institution or country"
                   value="<?php echo htmlspecialchars($wSearch); ?>">
        </label>
        <label>
            <span class="pma-vh">Session</span>
            <select name="session">
                <option value="all"<?php echo $wSession === 'all' ? ' selected' : ''; ?>>All registrations</option>
                <option value="series"<?php echo $wSession === 'series' ? ' selected' : ''; ?>>Whole series only</option>
<?php foreach ($wSessions as $wRow): ?>
                <option value="<?php echo (int) $wRow['id']; ?>"<?php echo $wSession === (string) $wRow['id'] ? ' selected' : ''; ?>>
                    Session <?php echo str_pad((string) $wRow['session_number'], 2, '0', STR_PAD_LEFT); ?>:
                    <?php echo htmlspecialchars($wRow['title']); ?> (<?php echo htmlspecialchars(date('j M Y', strtotime((string) $wRow['session_date']))); ?>)
                </option>
<?php endforeach; ?>
            </select>
        </label>
        <label>
            <span class="pma-vh">Status</span>
            <select name="status">
                <option value="all"<?php echo $wStatus === 'all' ? ' selected' : ''; ?>>Any status</option>
                <option value="active"<?php echo $wStatus === 'active' ? ' selected' : ''; ?>>Active</option>
                <option value="unsubscribed"<?php echo $wStatus === 'unsubscribed' ? ' selected' : ''; ?>>Unsubscribed</option>
            </select>
        </label>
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
<?php if ($wFiltered): ?>
        <a href="registrations.php?tab=webinars" class="btn btn-outline btn-sm">Clear</a>
<?php endif; ?>
        <a href="registrations.php?<?php echo htmlspecialchars($wQuery(['export' => 'csv'])); ?>"
           class="btn btn-outline btn-sm" style="margin-left:auto"
           title="Downloads exactly what the current filters show">Export CSV<?php echo $wFiltered ? ' (filtered)' : ''; ?></a>
    </form>
</div>

<div class="table-card">
    <div class="table-card-header">
        <div>
            <div class="card-title"><?php echo $wTotal; ?> Webinar registration<?php echo $wTotal !== 1 ? 's' : ''; ?></div>
<?php if ($wSession !== 'all' && $wSession !== 'series'): ?>
            <div class="card-subtitle">Everyone who will be at this session, including whole-series sign-ups</div>
<?php elseif ($wFiltered): ?>
            <div class="card-subtitle">Filtered results</div>
<?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Registered</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Institution / Country</th>
                    <th>Registered for</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
<?php if ($wRows): ?>
<?php   foreach ($wRows as $r): ?>
<?php     $sid = (int) $r['session_id']; ?>
                <tr>
                    <td style="color:#6b6b6b;"><?php echo (int) $r['id']; ?></td>
                    <td style="white-space:nowrap;color:#6b6b6b;font-size:12.5px;">
                        <?php echo htmlspecialchars(date('M d, Y', strtotime((string) $r['created_at']))); ?><br>
                        <?php echo htmlspecialchars(date('H:i', strtotime((string) $r['created_at']))); ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars((string) $r['name']); ?></strong>
<?php     if ($r['unsubscribed_at']): ?>
                        <br><span class="badge badge-gray">Unsubscribed</span>
<?php     endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string) $r['email']); ?></td>
                    <td>
                        <?php echo htmlspecialchars(($r['organization'] ?? '') !== '' ? (string) $r['organization'] : '-'); ?><br>
                        <span style="color:#6b6b6b;font-size:12px;"><?php echo htmlspecialchars(($r['country'] ?? '') !== '' ? (string) $r['country'] : '-'); ?></span>
                    </td>
                    <td>
<?php     if ($sid === 0): ?>
                        <span class="badge badge-green">Whole series</span>
<?php     else: ?>
                        <a href="registrations.php?<?php echo htmlspecialchars($wQuery(['session' => (string) $sid, 'page' => ''])); ?>">
                            Session <?php echo htmlspecialchars(str_pad((string) ($wTitles[$sid]['session_number'] ?? $sid), 2, '0', STR_PAD_LEFT)); ?></a><br>
                        <span style="color:#6b6b6b;font-size:12px;"><?php echo htmlspecialchars((string) ($wTitles[$sid]['title'] ?? '')); ?></span>
<?php     endif; ?>
                    </td>
                    <td class="pma-rowactions-cell">
                        <div class="pma-rowactions">
<?php     if (hasPermission('registrations', 'delete')): ?>
                            <form method="POST"
                                  onsubmit="return confirm('Delete this webinar registration permanently? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="delete_webinar_id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">Delete</button>
                            </form>
<?php     endif; ?>
                        </div>
                    </td>
                </tr>
<?php   endforeach; ?>
<?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="empty-state-title">No webinar registrations match</span>
                            Clear the filters above, or wait for the next sign-up.
                        </div>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>

<?php if ($wPages > 1): ?>
    <div style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:6px;justify-content:center;border-top:1px solid var(--gray-200);">
<?php   for ($i = 1; $i <= $wPages; $i++): ?>
        <a href="registrations.php?<?php echo htmlspecialchars($wQuery(['page' => (string) $i])); ?>"
           class="btn <?php echo $i === $wPage ? 'btn-primary' : 'btn-outline'; ?> btn-sm"
           style="min-width:36px;justify-content:center;"><?php echo $i; ?></a>
<?php   endfor; ?>
    </div>
<?php endif; ?>
</div>

<?php include 'footer.php'; ?>
