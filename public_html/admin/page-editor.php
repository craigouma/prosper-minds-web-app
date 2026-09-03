<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/pages.php';
require_once '../includes/trash.php';
requireAdminAuth();
requirePermission('content', 'view');

ensurePagesSchema($pdo);

$pageId = (int) ($_GET['id'] ?? 0);
$page   = pmPageFind($pdo, $pageId);

if (!$page) {
    header('Location: pages.php');
    exit;
}

$pageTitle  = $page['title'];
$activePage = 'pages';

$notice   = '';
$error    = '';
$who      = (string) ($_SESSION['admin_username'] ?? 'unknown');
$canEdit  = hasPermission('content', 'edit');
$canPub   = hasPermission('content', 'publish');
$selected = (int) ($_GET['block'] ?? 0);

/** Field definitions per block type, so the editor and the renderer agree. */
function pmBlockFields(string $type): array
{
    $common = [
        'heading' => ['label' => 'Heading', 'type' => 'text'],
        'body'    => ['label' => 'Body',    'type' => 'rich'],
    ];

    return match ($type) {
        'hero' => [
            'eyebrow'   => ['label' => 'Eyebrow', 'type' => 'text'],
            'heading'   => ['label' => 'Heading', 'type' => 'text'],
            'body'      => ['label' => 'Body',    'type' => 'textarea'],
            'cta_label' => ['label' => 'Button label', 'type' => 'text'],
            'cta_href'  => ['label' => 'Button link',  'type' => 'text'],
        ],
        'richtext' => $common,
        'image' => [
            'src'     => ['label' => 'Image address', 'type' => 'media'],
            'alt'     => ['label' => 'Alt text',      'type' => 'text'],
            'caption' => ['label' => 'Caption',       'type' => 'text'],
        ],
        'imagetext' => [
            'heading' => ['label' => 'Heading', 'type' => 'text'],
            'body'    => ['label' => 'Body',    'type' => 'rich'],
            'src'     => ['label' => 'Image address', 'type' => 'media'],
            'alt'     => ['label' => 'Alt text',      'type' => 'text'],
        ],
        'stats' => [
            'heading' => ['label' => 'Heading', 'type' => 'text'],
            'items'   => ['label' => 'Figures', 'type' => 'lines',
                          'hint' => 'One per line, as figure | label. For example: 875 | officials trained'],
        ],
        'cards' => [
            'heading' => ['label' => 'Heading', 'type' => 'text'],
            'items'   => ['label' => 'Cards',   'type' => 'lines',
                          'hint' => 'One per line, as title | description'],
        ],
        'testimonials' => [
            'heading' => ['label' => 'Heading',      'type' => 'text'],
            'items'   => ['label' => 'Testimonials', 'type' => 'lines',
                          'hint' => 'One per line, as quote | who said it'],
        ],
        'agenda' => [
            'heading' => ['label' => 'Heading', 'type' => 'text'],
            'items'   => ['label' => 'Rows',    'type' => 'lines',
                          'hint' => 'One per line, as title | description'],
        ],
        'cta' => [
            'heading'   => ['label' => 'Heading', 'type' => 'text'],
            'body'      => ['label' => 'Body',    'type' => 'textarea'],
            'cta_label' => ['label' => 'Button label', 'type' => 'text'],
            'cta_href'  => ['label' => 'Button link',  'type' => 'text'],
        ],
        'eventlist' => ['heading' => ['label' => 'Heading', 'type' => 'text']],
        'contact'   => [
            'heading' => ['label' => 'Heading', 'type' => 'text'],
            'body'    => ['label' => 'Body',    'type' => 'textarea'],
        ],
        'embed' => [
            'url'   => ['label' => 'Embed address', 'type' => 'text',
                        'hint' => 'An https address from YouTube, Vimeo or Google Maps. Nothing else is embedded.'],
            'title' => ['label' => 'Description for screen readers', 'type' => 'text'],
        ],
        default => $common,
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        requirePermission('content', 'edit');
        $action  = (string) ($_POST['action'] ?? '');
        $blockId = (int) ($_POST['block_id'] ?? 0);

        if ($action === 'add_block') {
            $type = isset(PM_BLOCK_TYPES[$_POST['block_type'] ?? '']) ? $_POST['block_type'] : 'richtext';
            $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_page_blocks WHERE page_id = ' . (int) $pageId)->fetchColumn();
            $pdo->prepare('INSERT INTO cms_page_blocks (page_id, block_type, sort_order, payload) VALUES (?, ?, ?, ?)')
                ->execute([$pageId, $type, $next, '{}']);
            $selected = (int) $pdo->lastInsertId();
            pmAudit($pdo, 'block_add', 'Added a ' . PM_BLOCK_TYPES[$type] . ' block to "' . $page['title'] . '"', 'cms_pages', $pageId);
            $notice = 'Block added.';
        }

        if ($action === 'save_block' && $blockId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM cms_page_blocks WHERE id = ? AND page_id = ?');
            $stmt->execute([$blockId, $pageId]);
            $block = $stmt->fetch();

            if ($block) {
                $data = [];
                foreach (array_keys(pmBlockFields($block['block_type'])) as $field) {
                    $data[$field] = mb_substr((string) ($_POST['f_' . $field] ?? ''), 0, 20000);
                }
                $appearance = ($_POST['appearance'] ?? 'light') === 'dark' ? 'dark' : 'light';

                pmRevisionStore($pdo, 'page', $pageId, pmPageSnapshot($pdo, $pageId), 'Before editing a block', $who);
                $pdo->prepare('UPDATE cms_page_blocks SET payload = ?, appearance = ? WHERE id = ?')
                    ->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $appearance, $blockId]);
                $pdo->prepare('UPDATE cms_pages SET updated_by = ? WHERE id = ?')->execute([$who, $pageId]);
                pmAudit($pdo, 'block_edit', 'Edited a block on "' . $page['title'] . '"', 'cms_pages', $pageId);
                $notice   = 'Block saved.';
                $selected = $blockId;
            }
        }

        if (($action === 'up' || $action === 'down') && $blockId > 0) {
            $blocks = pmPageBlocks($pdo, $pageId);
            $index  = null;
            foreach ($blocks as $i => $b) {
                if ((int) $b['id'] === $blockId) { $index = $i; break; }
            }
            $swap = $action === 'up' ? $index - 1 : $index + 1;

            if ($index !== null && isset($blocks[$swap])) {
                $a = $blocks[$index];
                $b = $blocks[$swap];
                $stmt = $pdo->prepare('UPDATE cms_page_blocks SET sort_order = ? WHERE id = ?');
                $stmt->execute([(int) $b['sort_order'], (int) $a['id']]);
                $stmt->execute([(int) $a['sort_order'], (int) $b['id']]);
            }
            $selected = $blockId;
        }

        if ($action === 'delete_block' && $blockId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM cms_page_blocks WHERE id = ? AND page_id = ?');
            $stmt->execute([$blockId, $pageId]);
            $doomed = $stmt->fetch();

            if ($doomed && pmTrashPut($pdo, 'block', $blockId,
                    (PM_BLOCK_TYPES[$doomed['block_type']] ?? $doomed['block_type']) . ' block',
                    $doomed, $who, 'On the page "' . $page['title'] . '"')) {
                pmRevisionStore($pdo, 'page', $pageId, pmPageSnapshot($pdo, $pageId), 'Before removing a block', $who);
                $pdo->prepare('DELETE FROM cms_page_blocks WHERE id = ? AND page_id = ?')->execute([$blockId, $pageId]);
                pmAudit($pdo, 'block_delete', 'Moved a block from "' . $page['title'] . '" to the trash', 'cms_pages', $pageId);
                $notice   = 'Block moved to the trash. It can be restored for the next 30 days.';
                $selected = 0;
            } else {
                $error = 'That block could not be removed.';
            }
        }

        if ($action === 'save_settings') {
            pmRevisionStore($pdo, 'page', $pageId, pmPageSnapshot($pdo, $pageId), 'Before editing page settings', $who);
            $pdo->prepare('UPDATE cms_pages SET title = ?, seo_title = ?, seo_description = ?, noindex = ?, updated_by = ? WHERE id = ?')
                ->execute([
                    mb_substr(trim((string) $_POST['title']), 0, 180) ?: $page['title'],
                    mb_substr(trim((string) $_POST['seo_title']), 0, 255) ?: null,
                    mb_substr(trim((string) $_POST['seo_description']), 0, 320) ?: null,
                    isset($_POST['noindex']) ? 1 : 0,
                    $who, $pageId,
                ]);
            pmAudit($pdo, 'page_settings', 'Updated the settings of "' . $page['title'] . '"', 'cms_pages', $pageId);
            $notice = 'Page settings saved.';
        }

        if ($action === 'publish' || $action === 'unpublish' || $action === 'schedule') {
            requirePermission('content', 'publish');
            if ($action === 'publish') {
                $pdo->prepare("UPDATE cms_pages SET status = 'published', publish_at = NULL, updated_by = ? WHERE id = ?")
                    ->execute([$who, $pageId]);
                $notice = 'Published. It is live now.';
            } elseif ($action === 'unpublish') {
                $pdo->prepare("UPDATE cms_pages SET status = 'draft', updated_by = ? WHERE id = ?")->execute([$who, $pageId]);
                $notice = 'Back to draft. It is no longer public.';
            } else {
                $when = trim((string) ($_POST['publish_at'] ?? ''));
                $ts   = $when !== '' ? strtotime($when) : false;
                if ($ts === false) {
                    $error = 'That is not a date this can use.';
                } else {
                    $pdo->prepare("UPDATE cms_pages SET status = 'scheduled', publish_at = ?, updated_by = ? WHERE id = ?")
                        ->execute([date('Y-m-d H:i:s', $ts), $who, $pageId]);
                    $notice = 'Scheduled for ' . date('j M Y, H:i', $ts) . '.';
                }
            }
            pmAudit($pdo, 'page_' . $action, ucfirst($action) . ' "' . $page['title'] . '"', 'cms_pages', $pageId);
        }

        if ($action === 'preview_link') {
            $token = pmPreviewTokenCreate($pdo, $pageId, $who);
            $notice = $token !== null
                ? 'Share this link. It stops working in 48 hours: /' . $page['slug'] . '?preview=' . $token
                : 'A preview link could not be created.';
        }

        if ($action === 'restore_revision') {
            $revId = (int) ($_POST['revision_id'] ?? 0);
            $notice = pmRevisionRestore($pdo, $revId, $who)
                ? 'Restored. The version you replaced was itself saved, so this can be undone.'
                : 'That version could not be restored.';
            pmAudit($pdo, 'page_restore_revision', 'Restored a version of "' . $page['title'] . '"', 'cms_pages', $pageId);
        }

        $page = pmPageFind($pdo, $pageId) ?: $page;
    }
}

$blocks    = pmPageBlocks($pdo, $pageId);
$revisions = pmRevisionList($pdo, 'page', $pageId, 20);
$csrfToken = generateCsrfToken();

$current = null;
foreach ($blocks as $b) {
    if ((int) $b['id'] === $selected) { $current = $b; }
}
if ($current === null && $blocks) {
    $current  = $blocks[0];
    $selected = (int) $current['id'];
}

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="pma-toolbar" style="border:0;padding:0 0 16px">
  <a class="btn btn-outline btn-sm" href="pages.php">All pages</a>
  <span class="badge <?php echo pmPageIsLive($page) ? 'badge-green' : 'badge-gray'; ?>">
    <?php echo htmlspecialchars(PM_PAGE_STATUSES[$page['status']] ?? $page['status']); ?>
  </span>
  <span class="text-muted" style="font-size:12px">/<?php echo htmlspecialchars($page['slug']); ?></span>

  <span style="margin-left:auto"></span>
<?php if ($canEdit): ?>
  <form method="POST" style="display:inline">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <button type="submit" name="action" value="preview_link" class="btn btn-outline btn-sm">Get a preview link</button>
  </form>
<?php endif; ?>
<?php if ($canPub): ?>
  <form method="POST" style="display:inline">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
<?php if (pmPageIsLive($page)): ?>
    <button type="submit" name="action" value="unpublish" class="btn btn-outline btn-sm">Unpublish</button>
<?php else: ?>
    <button type="submit" name="action" value="publish" class="btn btn-primary btn-sm">Publish</button>
<?php endif; ?>
  </form>
<?php endif; ?>
</div>

<div class="pma-editor">

  <div class="table-card" style="margin-bottom:0">
    <div class="table-card-header"><h2 class="card-title">Blocks</h2></div>
<?php if (!$blocks): ?>
    <div class="empty-state" style="padding:20px 14px">
      <span class="empty-state-title">Empty page</span>
      Add a block to start.
    </div>
<?php else: ?>
    <ul class="pma-blocklist">
<?php foreach ($blocks as $i => $b): ?>
      <li class="pma-blockitem <?php echo (int) $b['id'] === $selected ? 'is-selected' : ''; ?>">
        <a href="page-editor.php?id=<?php echo $pageId; ?>&block=<?php echo (int) $b['id']; ?>">
          <span class="pma-blockitem-name"><?php echo htmlspecialchars(PM_BLOCK_TYPES[$b['block_type']] ?? $b['block_type']); ?></span>
          <span class="pma-blockitem-mode <?php echo $b['appearance'] === 'dark' ? 'is-dark' : ''; ?>"><?php
            echo $b['appearance'] === 'dark' ? 'Dark' : 'Light'; ?></span>
        </a>
<?php if ($canEdit): ?>
        <form method="POST" style="display:flex;gap:2px">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="block_id" value="<?php echo (int) $b['id']; ?>">
          <button type="submit" name="action" value="up" class="btn btn-icon btn-sm" aria-label="Move up"
                  <?php echo $i === 0 ? 'disabled' : ''; ?>>&#8593;</button>
          <button type="submit" name="action" value="down" class="btn btn-icon btn-sm" aria-label="Move down"
                  <?php echo $i === count($blocks) - 1 ? 'disabled' : ''; ?>>&#8595;</button>
        </form>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($canEdit): ?>
    <form method="POST" style="padding:12px 14px;border-top:1px solid var(--pma-border)">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <input type="hidden" name="action" value="add_block">
      <div class="form-group">
        <label for="block_type">Add a block</label>
        <select id="block_type" name="block_type" class="form-control">
<?php foreach (PM_BLOCK_TYPES as $key => $label): ?>
          <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-outline btn-sm">Add</button>
      <p class="form-hint" style="margin-top:8px">Twelve types, each matching a section this site already has.
         There is no free HTML block, which is what keeps a page from being made ugly by accident.</p>
    </form>
<?php endif; ?>
  </div>

  <div class="table-card" style="margin-bottom:0">
    <div class="table-card-header">
      <h2 class="card-title"><?php echo $current
        ? htmlspecialchars(PM_BLOCK_TYPES[$current['block_type']] ?? $current['block_type'])
        : 'Nothing selected'; ?></h2>
    </div>

<?php if ($current && $canEdit): ?>
    <form method="POST" style="padding:16px">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <input type="hidden" name="action" value="save_block">
      <input type="hidden" name="block_id" value="<?php echo (int) $current['id']; ?>">

<?php foreach (pmBlockFields($current['block_type']) as $field => $meta):
        $value = (string) ($current['data'][$field] ?? '');
?>
      <div class="form-group">
        <label for="f_<?php echo $field; ?>"><?php echo htmlspecialchars($meta['label']); ?></label>
<?php   if ($meta['type'] === 'rich'): ?>
        <div class="pma-rte-tools" aria-hidden="true">
          <span>H2</span><span>H3</span><span>B</span><span>I</span><span>Link</span><span>List</span><span>Quote</span>
        </div>
        <textarea id="f_<?php echo $field; ?>" name="f_<?php echo $field; ?>" class="form-control"
                  rows="7"><?php echo htmlspecialchars($value); ?></textarea>
        <span class="form-hint">Headings, bold, italic, links, lists and quotes only. No fonts, sizes or colours,
              which is what keeps every page looking like the same site.</span>
<?php   elseif ($meta['type'] === 'lines'): ?>
        <textarea id="f_<?php echo $field; ?>" name="f_<?php echo $field; ?>" class="form-control"
                  rows="6"><?php echo htmlspecialchars($value); ?></textarea>
<?php   elseif ($meta['type'] === 'textarea'): ?>
        <textarea id="f_<?php echo $field; ?>" name="f_<?php echo $field; ?>" class="form-control"
                  rows="4"><?php echo htmlspecialchars($value); ?></textarea>
<?php   elseif ($meta['type'] === 'media'): ?>
        <input type="text" id="f_<?php echo $field; ?>" name="f_<?php echo $field; ?>" class="form-control"
               value="<?php echo htmlspecialchars($value); ?>" placeholder="/assets/uploads/...">
        <span class="form-hint">Paste the address from the <a href="media.php">media library</a>.</span>
<?php   else: ?>
        <input type="text" id="f_<?php echo $field; ?>" name="f_<?php echo $field; ?>" class="form-control"
               value="<?php echo htmlspecialchars($value); ?>">
<?php   endif; ?>
<?php   if (!empty($meta['hint'])): ?>
        <span class="form-hint"><?php echo htmlspecialchars($meta['hint']); ?></span>
<?php   endif; ?>
      </div>
<?php endforeach; ?>

      <div class="form-group">
        <label for="appearance">Appearance</label>
        <select id="appearance" name="appearance" class="form-control">
          <option value="light" <?php echo $current['appearance'] === 'light' ? 'selected' : ''; ?>>Light</option>
          <option value="dark"  <?php echo $current['appearance'] === 'dark'  ? 'selected' : ''; ?>>Dark</option>
        </select>
        <span class="form-hint">One choice per block, which is how the public site is already built.</span>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">Save block</button>
    </form>

    <form method="POST" style="padding:0 16px 16px">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <input type="hidden" name="action" value="delete_block">
      <input type="hidden" name="block_id" value="<?php echo (int) $current['id']; ?>">
      <button type="submit" class="btn btn-danger btn-sm"
              data-confirm="Move this block to the trash? You can restore it for 30 days.">Remove this block</button>
    </form>
<?php elseif (!$current): ?>
    <div class="empty-state" style="padding:24px 16px">Add a block, then select it to edit.</div>
<?php endif; ?>
  </div>

  <aside style="display:flex;flex-direction:column;gap:22px">
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px">Page settings</h2>
<?php if ($canEdit): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="save_settings">
        <div class="form-group">
          <label for="p_title">Title</label>
          <input type="text" id="p_title" name="title" class="form-control" maxlength="180"
                 value="<?php echo htmlspecialchars($page['title']); ?>">
        </div>
        <div class="form-group">
          <label for="p_seo_title">Search title</label>
          <input type="text" id="p_seo_title" name="seo_title" class="form-control" maxlength="255"
                 value="<?php echo htmlspecialchars((string) $page['seo_title']); ?>">
          <span class="form-hint">Around 60 characters reads well in a search result.</span>
        </div>
        <div class="form-group">
          <label for="p_seo_desc">Search description</label>
          <textarea id="p_seo_desc" name="seo_description" class="form-control" rows="3"
                    maxlength="320"><?php echo htmlspecialchars((string) $page['seo_description']); ?></textarea>
          <span class="form-hint">Around 155 characters. Left empty, search engines write their own.</span>
        </div>
        <label class="check-group-sub" style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
          <input type="checkbox" name="noindex" value="1" <?php echo $page['noindex'] ? 'checked' : ''; ?>>
          Ask search engines not to list this page
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Save settings</button>
      </form>
<?php endif; ?>
    </div>

<?php if ($canPub): ?>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px">Publish later</h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="schedule">
        <div class="form-group">
          <label for="publish_at">Go live at</label>
          <input type="datetime-local" id="publish_at" name="publish_at" class="form-control"
                 value="<?php echo $page['publish_at'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($page['publish_at']))) : ''; ?>">
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Schedule</button>
      </form>
    </div>
<?php endif; ?>

    <div class="card">
      <h2 class="card-title" style="margin-bottom:4px">History</h2>
      <p class="card-subtitle" style="margin-bottom:12px"><?php echo count($revisions); ?> version<?php
        echo count($revisions) === 1 ? '' : 's'; ?> kept</p>
<?php if (!$revisions): ?>
      <span class="form-hint">Nothing yet. A version is kept every time this page changes.</span>
<?php else: ?>
      <ul class="pma-revisions">
<?php foreach ($revisions as $rev): ?>
        <li>
          <div>
            <span class="pma-revisions-note"><?php echo htmlspecialchars((string) $rev['note']); ?></span>
            <span class="pma-revisions-meta">
              <?php echo htmlspecialchars(date('j M, H:i', strtotime($rev['created_at']))); ?>
<?php if ($rev['author'] === 'admin'): ?>
              by the shared admin account, which cannot be attributed to a person
<?php else: ?>
              by <?php echo htmlspecialchars((string) $rev['author']); ?>
<?php endif; ?>
            </span>
          </div>
<?php if ($canEdit): ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="restore_revision">
            <input type="hidden" name="revision_id" value="<?php echo (int) $rev['id']; ?>">
            <button type="submit" class="btn btn-outline btn-sm">Restore</button>
          </form>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
      <p class="form-hint" style="margin-top:10px">Restoring writes a new version rather than overwriting, so a
         restore can itself be undone.</p>
<?php endif; ?>
    </div>
  </aside>
</div>

<?php require_once 'footer.php'; ?>
