<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/invoice.php';
requireAdminAuth();
requirePermission('events', 'view');

$pageTitle  = 'Early bird control';
$activePage = 'earlybird';

$notice = '';
$error  = '';
$who    = (string) ($_SESSION['admin_username'] ?? 'unknown');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        requirePermission('events', 'edit');

        if (($_POST['action'] ?? '') === 'save_tiers') {
            $id = (int) ($_POST['event_id'] ?? 0);
            $pdo->prepare('UPDATE events SET early_bird_1_pct = ?, early_bird_1_date = ?,
                                             early_bird_2_pct = ?, early_bird_2_date = ?,
                                             early_bird_3_pct = ?, early_bird_3_date = ?
                            WHERE id = ?')
                ->execute([
                    max(0, min(100, (int) $_POST['p1'])), ($_POST['d1'] ?? '') ?: null,
                    max(0, min(100, (int) $_POST['p2'])), ($_POST['d2'] ?? '') ?: null,
                    max(0, min(100, (int) $_POST['p3'])), ($_POST['d3'] ?? '') ?: null,
                    $id,
                ]);
            pmAudit($pdo, 'earlybird_tiers', 'Changed the early bird tiers on event #' . $id, 'events', $id);
            $notice = 'Tiers saved. What the site advertises has changed.';
        }

        if (($_POST['action'] ?? '') === 'save_policy' && isSuper()) {
            $apply = isset($_POST['apply_to_invoices']) ? '1' : '0';
            $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('early_bird_apply', ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$apply]);
            pmAudit($pdo, 'earlybird_policy', 'Set the early bird invoicing policy to ' . ($apply ? 'apply' : 'do not apply'));
            $notice = 'Policy recorded.';
        }
    }
}

$applyPolicy = '0';
try {
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'early_bird_apply'");
    $applyPolicy = (string) ($stmt->fetchColumn() ?: '0');
} catch (Throwable $e) {
    error_log('earlybird: could not read the policy: ' . $e->getMessage());
}

$events = [];
try {
    $events = $pdo->query('SELECT * FROM events WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
} catch (Throwable $e) {
    $error = 'The events could not be read.';
}

/** The tier in force today, or null when every deadline has passed. */
function pmEbCurrent(array $event): ?array
{
    $today = date('Y-m-d');

    foreach ([1, 2, 3] as $n) {
        $pct  = (int) ($event['early_bird_' . $n . '_pct'] ?? 0);
        $date = (string) ($event['early_bird_' . $n . '_date'] ?? '');

        if ($pct > 0 && $date !== '' && $date >= $today) {
            return ['tier' => $n, 'pct' => $pct, 'date' => $date];
        }
    }

    return null;
}

$billedCount = 0;
try {
    $billedCount = (int) $pdo->query('SELECT COUNT(*) FROM event_registrations')->fetchColumn();
} catch (Throwable $e) {
    $billedCount = 0;
}

$csrfToken = generateCsrfToken();
$canEdit   = hasPermission('events', 'edit');

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="alert alert-danger">
  <div>
    <strong>What the site promises and what the invoice charges do not agree.</strong><br>
    The pages advertise these discounts, and nothing in the invoicing path reads them. Every one of the
    <?php echo $billedCount; ?> registrations taken so far was billed the full price. Turning the switch below on
    records the decision; the pricing change that acts on it is a separate deployment, because the handler that
    prices a registration is the one that failed in August and is not edited casually.
  </div>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2 class="card-title">Invoicing policy</h2>
      <p class="card-subtitle">One decision, applied to every event</p>
    </div>
  </div>
  <div style="padding:16px">
<?php if (isSuper()): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <input type="hidden" name="action" value="save_policy">
      <label class="check-group-sub" style="display:flex;gap:10px;align-items:flex-start;margin-bottom:14px">
        <input type="checkbox" name="apply_to_invoices" value="1" <?php echo $applyPolicy === '1' ? 'checked' : ''; ?>>
        <span>Apply the advertised early bird discount to invoices.<br>
          <span class="text-muted">Currently <strong><?php echo $applyPolicy === '1' ? 'on' : 'off'; ?></strong>.
          While this is off, a delegate who registers inside an open window is invoiced the full price.</span></span>
      </label>
      <button type="submit" class="btn btn-primary btn-sm">Record this decision</button>
    </form>
<?php else: ?>
    <p class="form-hint">The policy is currently
      <strong><?php echo $applyPolicy === '1' ? 'to apply' : 'not to apply'; ?></strong>
      early bird discounts to invoices. Only a super admin can change it.</p>
<?php endif; ?>
  </div>
</div>

<?php foreach ($events as $ev):
    $current  = pmEbCurrent($ev);
    [$ccy, $unit] = parseEventPrice((string) ($ev['price'] ?? ''));
    $advertised = $current ? round($unit * (1 - $current['pct'] / 100), 2) : $unit;
    $charged    = $applyPolicy === '1' ? $advertised : $unit;
?>
<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2 class="card-title"><?php echo htmlspecialchars($ev['title']); ?></h2>
      <p class="card-subtitle"><?php echo htmlspecialchars((string) $ev['date_display']); ?>,
         <?php echo htmlspecialchars((string) $ev['location']); ?></p>
    </div>
  </div>

  <div class="stats-grid" style="margin:0;border:0;border-bottom:1px solid var(--pma-border);border-radius:0">
    <div class="stat-card">
      <div class="stat-info">
        <h3><?php echo htmlspecialchars($ccy . ' ' . number_format($unit, 2)); ?></h3>
        <p>List price per delegate</p>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h3><?php echo $current ? $current['pct'] . '%' : 'None'; ?></h3>
        <p><?php echo $current ? 'Tier ' . $current['tier'] . ' open until ' . htmlspecialchars($current['date']) : 'No window open'; ?></p>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h3><?php echo htmlspecialchars($ccy . ' ' . number_format($advertised, 2)); ?></h3>
        <p>What the site advertises</p>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h3 style="<?php echo abs($charged - $advertised) > 0.001 ? 'color:var(--pma-alert)' : ''; ?>"><?php
          echo htmlspecialchars($ccy . ' ' . number_format($charged, 2)); ?></h3>
        <p><?php echo abs($charged - $advertised) > 0.001 ? 'What the invoice charges' : 'What the invoice charges'; ?></p>
      </div>
    </div>
  </div>

<?php if ($canEdit): ?>
  <form method="POST" style="padding:16px">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="action" value="save_tiers">
    <input type="hidden" name="event_id" value="<?php echo (int) $ev['id']; ?>">
    <div class="form-grid">
<?php foreach ([1, 2, 3] as $n): ?>
      <div class="form-group">
        <label for="p<?php echo $n . '_' . $ev['id']; ?>">Tier <?php echo $n; ?> discount</label>
        <input type="number" id="p<?php echo $n . '_' . $ev['id']; ?>" name="p<?php echo $n; ?>"
               class="form-control" min="0" max="100"
               value="<?php echo (int) $ev['early_bird_' . $n . '_pct']; ?>">
      </div>
      <div class="form-group">
        <label for="d<?php echo $n . '_' . $ev['id']; ?>">Tier <?php echo $n; ?> deadline</label>
        <input type="date" id="d<?php echo $n . '_' . $ev['id']; ?>" name="d<?php echo $n; ?>"
               class="form-control"
               value="<?php echo htmlspecialchars((string) $ev['early_bird_' . $n . '_date']); ?>">
      </div>
<?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Save these tiers</button>
    <span class="form-hint" style="display:inline-block;margin-left:10px">Changing a tier changes what the site
      advertises straight away.</span>
  </form>
<?php endif; ?>
</div>
<?php endforeach; ?>

<?php require_once 'footer.php'; ?>
