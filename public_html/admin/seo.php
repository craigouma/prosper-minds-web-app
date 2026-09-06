<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/content.php';
require_once '../includes/schema.php';
require_once '../includes/invoice.php';
requireAdminAuth();
requirePermission('seo', 'view');

$pageTitle  = 'SEO and structured data';
$activePage = 'seo';

ensurePageContentSchema($pdo);

$PAGES = [
    'home'        => ['label' => 'Homepage',    'path' => '/index.php'],
    'about'       => ['label' => 'About',       'path' => '/about.php'],
    'services'    => ['label' => 'Services',    'path' => '/services.php'],
    'events'      => ['label' => 'Events',      'path' => '/events.php'],
    'sponsorship' => ['label' => 'Sponsorship', 'path' => '/sponsorship.php'],
    'contact'     => ['label' => 'Contact',     'path' => '/contact.php'],
    'register'    => ['label' => 'Register',    'path' => '/event-registration.php'],
];

$notice = '';
$error  = '';
$slug   = isset($PAGES[$_GET['page'] ?? '']) ? (string) $_GET['page'] : 'home';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        requirePermission('seo', 'edit');
        $target = isset($PAGES[$_POST['page'] ?? '']) ? (string) $_POST['page'] : 'home';

        foreach (['meta_title', 'meta_description'] as $key) {
            pmContentSet($pdo, $target, $key, mb_substr(trim((string) ($_POST[$key] ?? '')), 0, 320), 'text');
        }
        pmAudit($pdo, 'seo_edit', 'Updated the search listing for the ' . $PAGES[$target]['label'] . ' page',
                'page_content', $target);
        $notice = 'Saved. Search engines pick this up the next time they visit.';
        $slug   = $target;
    }
}

$title = pmContent($pdo, $slug, 'meta_title', '');
$desc  = pmContent($pdo, $slug, 'meta_description', '');

$events = [];
try {
    $events = $pdo->query('SELECT * FROM events WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
} catch (Throwable $e) {
    $events = [];
}

$origin    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
           . '://' . ($_SERVER['HTTP_HOST'] ?? 'prosper-minds.com');
$csrfToken = generateCsrfToken();
$canEdit   = hasPermission('seo', 'edit');

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="pma-split">
  <div>
    <div class="table-card" style="margin-bottom:22px">
      <div class="table-card-header">
        <div>
          <h2 class="card-title">Search appearance</h2>
          <p class="card-subtitle">How one page looks in a search result. Pages built in the CMS carry their own
             fields in the page editor instead.</p>
        </div>
      </div>

      <form method="GET" action="seo.php" class="pma-toolbar">
        <label>
          <span class="pma-vh">Page</span>
          <select name="page">
<?php foreach ($PAGES as $key => $meta): ?>
            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $slug === $key ? 'selected' : ''; ?>><?php
              echo htmlspecialchars($meta['label']); ?></option>
<?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn btn-outline btn-sm">Open</button>
      </form>

      <div style="padding:16px">
        <span class="pma-label">Result preview</span>
<?php
$pmHost  = parse_url($origin, PHP_URL_HOST) ?: 'prosper-minds.com';
$pmCrumb = $pmHost . ' › ' . trim(str_replace(['/', '.php'], [' › ', ''], $PAGES[$slug]['path']), ' ›');
?>
        <div class="pma-serp">
          <div class="pma-serp-brand">
            <span class="pma-serp-favicon"><img src="../assets/images/favicon-32.png" alt=""></span>
            <span>
              <span class="pma-serp-site">Prosperminds</span><br>
              <span class="pma-serp-url"><?php echo htmlspecialchars($pmCrumb); ?></span>
            </span>
          </div>
          <div class="pma-serp-title"><?php
            echo htmlspecialchars($title !== '' ? mb_strimwidth($title, 0, 62, '...') : 'Prosperminds'); ?></div>
          <div class="pma-serp-desc"><?php
            echo htmlspecialchars($desc !== '' ? mb_strimwidth($desc, 0, 160, '...')
              : 'No description is set, so a search engine will write its own from whatever it finds on the page.'); ?></div>
        </div>

<?php if ($canEdit): ?>
        <form method="POST" action="seo.php" style="margin-top:18px">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="page" value="<?php echo htmlspecialchars($slug); ?>">
          <div class="form-group">
            <label for="meta_title">Search title</label>
            <input type="text" id="meta_title" name="meta_title" class="form-control" maxlength="320"
                   value="<?php echo htmlspecialchars($title); ?>">
            <span class="form-hint <?php echo mb_strlen($title) > 60 ? 'pma-count-over' : ''; ?>"><?php
              echo mb_strlen($title); ?> characters. Around 60 fits before a search engine cuts it off.</span>
          </div>
          <div class="form-group">
            <label for="meta_description">Search description</label>
            <textarea id="meta_description" name="meta_description" class="form-control" rows="3"
                      maxlength="320"><?php echo htmlspecialchars($desc); ?></textarea>
            <span class="form-hint <?php echo mb_strlen($desc) > 155 ? 'pma-count-over' : ''; ?>"><?php
              echo mb_strlen($desc); ?> characters. Around 155 fits.</span>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </form>
<?php endif; ?>
      </div>
    </div>

    <div class="table-card" style="margin-bottom:0">
      <div class="table-card-header">
        <div>
          <h2 class="card-title">Event structured data</h2>
          <p class="card-subtitle">What search engines are told about each course. This is what puts dates,
             places and prices into a listing rather than a plain blue link.</p>
        </div>
      </div>

<?php if (!$events): ?>
      <div class="empty-state">
        <span class="empty-state-title">No active events</span>
        Structured data is generated per event, so there is nothing to show.
      </div>
<?php else: ?>
      <div class="table-responsive">
        <table>
          <thead><tr><th>Event</th><th>Start date</th><th>Markup</th><th>What is published</th></tr></thead>
          <tbody>
<?php foreach ($events as $ev):
        $start = pmSchemaStartDate($ev);
        [$ccy, $price] = parseEventPrice((string) ($ev['price'] ?? ''));
?>
            <tr>
              <td><?php echo htmlspecialchars($ev['title']); ?></td>
              <td class="text-muted"><?php echo $start ?? 'Not set'; ?></td>
              <td>
<?php if ($start === null): ?>
                <span class="badge badge-orange">Withheld</span>
<?php else: ?>
                <span class="badge badge-green">Published</span>
<?php endif; ?>
              </td>
              <td class="text-muted">
<?php if ($start === null): ?>
                This event has no start date, so nothing is published for it. Structured data with a wrong date
                is worse than none: people act on it and arrive in the wrong week.
<?php else: ?>
                Name, description, <?php echo htmlspecialchars($start); ?>,
                <?php echo htmlspecialchars((string) $ev['location']); ?>,
                and <?php echo htmlspecialchars($ccy . ' ' . number_format($price, 2)); ?> per delegate.
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px">Sitemap and robots</h2>
      <dl class="pma-facts">
        <div><dt>Sitemap</dt><dd><a href="/sitemap.xml" target="_blank" rel="noopener">/sitemap.xml</a></dd></div>
        <div><dt>Robots</dt><dd><a href="/robots.txt" target="_blank" rel="noopener">/robots.txt</a></dd></div>
      </dl>
      <p class="form-hint" style="margin-top:10px">The sitemap is generated from the live events each time it is
         requested, so it cannot go stale. The admin panel, the invoice directory and the uploads are excluded
         from both.</p>
    </div>
  </aside>
</div>

<?php require_once 'footer.php'; ?>
