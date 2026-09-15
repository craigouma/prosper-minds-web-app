<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/media.php';
require_once '../includes/newsletter.php';
require_once '../includes/campaigns.php';
requireAdminAuth();
requirePermission('content', 'edit');

$pageTitle  = 'Newsletter';
$activePage = 'newsletter';

ensureNewsletterSubscriberSchema($pdo);
ensureCampaignSchema($pdo);

$notice = '';
$error  = '';
$who    = (string) ($_SESSION['admin_username'] ?? 'unknown');
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        $action  = (string) ($_POST['action'] ?? '');
        $id      = (int) ($_POST['id'] ?? 0);
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body    = (string) ($_POST['body_html'] ?? '');
        $files   = array_values(array_filter((array) ($_POST['attachments'] ?? []), 'strlen'));

        // A file picked here is stored in the media library like any other
        // upload, which is what makes it reusable next time and keeps one set
        // of rules about what may be uploaded. pmMediaStore decides the type
        // from the magic bytes, not the name, so a .pdf that is really
        // something else is refused.
        $uploaded = false;
        if (($_FILES['attachment_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = pmMediaStore($pdo, $_FILES['attachment_file'], $who);

            if ($stored['ok']) {
                $files[]  = $stored['filename'];
                $uploaded = true;
                pmAudit($pdo, 'media_upload', 'Uploaded ' . $stored['filename'] . ' from the newsletter screen',
                    'cms_media', $stored['id']);
            } else {
                $error = $stored['error'];
            }
        }

        try {
            // Attaching happens whichever button was pressed, so a file picked
            // just before Send me a test is not silently dropped.
            if ($uploaded && $error === '' && $action !== 'save' && $id > 0) {
                $existing = pmCampaignById($pdo, $id);
                if ($existing !== null && ($existing['status'] ?? '') === 'draft') {
                    pmCampaignSave($pdo, $id, (string) $existing['subject'],
                        (string) $existing['body_html'], $files, $who);
                }
            }

            if ($error !== '') {
                $editId = $id;
            } elseif ($action === 'save') {
                if ($subject === '') {
                    $error = 'A newsletter needs a subject line.';
                } else {
                    $id = pmCampaignSave($pdo, $id ?: null, $subject, $body, $files, $who);
                    pmAudit($pdo, 'newsletter_save', 'Saved the newsletter "' . $subject . '"',
                        'newsletter_campaigns', $id);
                    $notice = 'Saved as a draft. Nothing has been sent.';
                    $editId = $id;
                }
            } elseif ($action === 'test') {
                $to = trim((string) ($_POST['test_email'] ?? ''));
                $campaign = pmCampaignById($pdo, $id);

                if ($campaign === null) {
                    $error = 'Save it first, then send yourself a test.';
                } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $error = 'That test address does not look right.';
                } else {
                    $attachments = pmCampaignAttachmentPayload($campaign);

                    $from = trim((string) getMailSetting('BREVO_FROM_EMAIL', 'brevo_from_email', ''))
                        ?: trim((string) getSetting('contact_email', 'info@prosper-minds.com'));

                    $sent = pmBrevoSend(
                        $to,
                        '',
                        '[TEST] ' . $campaign['subject'],
                        pmCampaignRenderEmail($pdo, '[TEST] ' . $campaign['subject'], (string) $campaign['body_html'], $to),
                        $from,
                        (string) getSetting('company_name', 'Prosperminds'),
                        $attachments,
                        $from
                    );

                    $notice = $sent['ok'] ? 'Test sent to ' . $to . '.' : '';
                    $error  = $sent['ok'] ? '' : $sent['error'];
                    $editId = $id;
                }
            } elseif ($action === 'send') {
                $result = pmCampaignQueue($pdo, $id);

                if ($result['ok']) {
                    pmAudit($pdo, 'newsletter_send',
                        'Queued a newsletter for ' . $result['queued'] . ' subscriber(s)',
                        'newsletter_campaigns', $id);
                    $notice = 'Queued for ' . $result['queued'] . ' subscriber(s). '
                        . 'They go out on the next sweep, within half an hour.';
                } else {
                    $error = $result['error'];
                    $editId = $id;
                }
            }
        } catch (Throwable $e) {
            error_log('newsletter screen: ' . $e->getMessage());
            $error = 'That could not be completed. The details are in the error log.';
        }
    }
}

$campaigns  = pmCampaignList($pdo);
$editing    = $editId > 0 ? pmCampaignById($pdo, $editId) : null;
$audience   = pmCampaignAudience($pdo);
$configured = pmBrevoConfigured();
$library    = $pdo->query('SELECT filename, original_name FROM cms_media ORDER BY id DESC LIMIT 200')
                   ->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once 'header.php';
?>

<?php if ($notice !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (!$configured): ?>
  <div class="alert alert-danger">
    No Brevo API key is set, so nothing can be sent yet. Add it under
    <a href="settings.php">Settings</a>, in the Newsletter sending card.
  </div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-info"><h3><?php echo count($audience); ?></h3><p>Subscribed</p></div></div>
  <div class="stat-card"><div class="stat-info"><h3><?php
    echo (int) $pdo->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE unsubscribed_at IS NOT NULL')->fetchColumn();
  ?></h3><p>Unsubscribed</p></div></div>
  <div class="stat-card"><div class="stat-info"><h3><?php echo count($campaigns); ?></h3><p>Newsletters</p></div></div>
  <div class="stat-card"><div class="stat-info"><h3><?php
    echo (int) $pdo->query('SELECT COUNT(*) FROM newsletter_campaign_recipients WHERE status = "pending"')->fetchColumn();
  ?></h3><p>Waiting to send</p></div></div>
</div>

<?php $isDraft = !$editing || ($editing['status'] ?? 'draft') === 'draft'; ?>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2 class="card-title"><?php echo $editing ? 'Edit newsletter' : 'Write a newsletter'; ?></h2>
      <p class="card-subtitle"><?php echo $isDraft
        ? 'Saved as a draft. Nothing goes out until you press Send to subscribers.'
        : 'Already sent, so it is kept as a record and can no longer be edited.'; ?></p>
    </div>
<?php if ($editing): ?>
    <a class="btn btn-sm" href="newsletter.php">Start a new one</a>
<?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="id" value="<?php echo (int) ($editing['id'] ?? 0); ?>">

    <div style="padding:18px 16px;">
      <div class="form-group">
        <label for="nl-subject">Subject</label>
        <input type="text" id="nl-subject" name="subject" class="form-control" maxlength="300"
               value="<?php echo htmlspecialchars((string) ($editing['subject'] ?? '')); ?>"
               <?php echo $isDraft ? 'required' : 'disabled'; ?>>
      </div>

      <div class="form-group">
        <label for="nl-body">Message</label>
        <textarea id="nl-body" name="body_html" class="form-control" rows="12"
                  <?php echo $isDraft ? '' : 'disabled'; ?>><?php
          echo htmlspecialchars((string) ($editing['body_html'] ?? '')); ?></textarea>
        <p class="form-hint">HTML is allowed. An unsubscribe footer is added to every copy automatically.</p>
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label for="nl-attach">Attachments</label>
          <select id="nl-attach" name="attachments[]" class="form-control" multiple size="4"
                  <?php echo $isDraft ? '' : 'disabled'; ?>>
<?php
$chosen = $editing ? pmCampaignAttachments($editing) : [];
foreach ($library as $item):
    $file = (string) ($item['filename'] ?? '');
    if ($file === '') { continue; }
?>
            <option value="<?php echo htmlspecialchars($file); ?>"
              <?php echo in_array($file, $chosen, true) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($item['original_name'] ?: $file)); ?>
            </option>
<?php endforeach; ?>
          </select>
          <p class="form-hint">From the media library. Hold Ctrl, or Command on a Mac, to pick more than one.</p>

          <label for="nl-upload" style="margin-top:14px;">Or upload one now</label>
          <input type="file" id="nl-upload" name="attachment_file" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.webp,.gif"
                 <?php echo $isDraft ? '' : 'disabled'; ?>>
          <p class="form-hint">PDF or an image, up to <?php
            echo htmlspecialchars(pmMediaHumanSize(pmMediaUploadLimitBytes())); ?>.
            It is added to the media library as well, so it is there next time.</p>
        </div>

        <div class="form-group">
          <label for="nl-test">Test address</label>
          <input type="email" id="nl-test" name="test_email" class="form-control"
                 value="<?php echo htmlspecialchars((string) getSetting('admin_email', '')); ?>"
                 <?php echo $isDraft ? '' : 'disabled'; ?>>
          <p class="form-hint">Always send yourself one first. A real send cannot be undone.</p>
        </div>
      </div>
    </div>

<?php if ($isDraft): ?>
    <div class="pma-toolbar" style="border-bottom:0;border-top:1px solid var(--pma-border);">
      <button type="submit" name="action" value="save" class="btn btn-primary btn-sm">Save draft</button>
<?php if ($editing): ?>
      <button type="submit" name="action" value="test" class="btn btn-sm">Send me a test</button>
      <span style="flex:1 1 auto;"></span>
      <button type="submit" name="action" value="send" class="btn btn-danger btn-sm"
              <?php echo ($configured && $audience !== []) ? '' : 'disabled'; ?>
              onclick="return confirm('Send to <?php echo count($audience); ?> subscriber(s)? This cannot be undone.');">
        Send to <?php echo count($audience); ?> subscriber(s)
      </button>
<?php endif; ?>
    </div>
<?php endif; ?>
  </form>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div><h2 class="card-title">Sent and drafts</h2></div>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Subject</th><th>Status</th><th>Sent</th><th>Failed</th><th>Written by</th><th>When</th><th></th></tr></thead>
      <tbody>
<?php if ($campaigns === []): ?>
        <tr><td colspan="7" class="text-muted">Nothing written yet.</td></tr>
<?php endif; ?>
<?php foreach ($campaigns as $c):
    $badge = $c['status'] === 'sent' ? 'badge-green' : ($c['status'] === 'sending' ? 'badge-orange' : ($c['status'] === 'failed' ? 'badge-red' : 'badge-gray'));
?>
        <tr>
          <td><?php echo htmlspecialchars((string) $c['subject']); ?></td>
          <td><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars((string) $c['status']); ?></span></td>
          <td><?php echo (int) $c['sent']; ?> of <?php echo (int) $c['total']; ?></td>
          <td><?php echo (int) $c['failed'] > 0
                ? '<span class="badge badge-red">' . (int) $c['failed'] . '</span>' : '0'; ?></td>
          <td class="text-muted"><?php echo htmlspecialchars((string) ($c['created_by'] ?? '')); ?></td>
          <td class="text-muted"><?php echo htmlspecialchars(date('j M Y H:i', strtotime((string) $c['created_at']))); ?></td>
          <td><a class="btn btn-sm btn-secondary" href="newsletter.php?edit=<?php echo (int) $c['id']; ?>">Open</a></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'footer.php'; ?>
