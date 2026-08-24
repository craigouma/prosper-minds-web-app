<?php
// Served at /sitemap.xml via the rewrite rule in .htaccess. Generated on each
// request from the events table rather than a static file, so it can never go
// stale when an event is added, removed, or unpublished — exactly the kind of
// drift that caused confusion in this codebase before (see PROJECT.md Section 3).
//
// Deliberately excludes event-registration.php and admin/tools paths: this is
// for pages worth ranking in search (content), not transactional or private
// ones.
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = 'https://prosper-minds.com';
$today = date('Y-m-d');

$staticPages = [
    ['loc' => '/index.php', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => '/service-pfm.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => '/service-data.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => '/service-sustainability.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => '/sponsorship.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
];

$events = [];
try {
    $events = $pdo->query(
        "SELECT id, created_at FROM events WHERE is_active = 1 ORDER BY sort_order, event_start_date, id"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('sitemap.php: could not load events (ignored, static pages still listed): ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
    <url>
        <loc><?php echo htmlspecialchars($baseUrl . $page['loc']); ?></loc>
        <lastmod><?php echo $today; ?></lastmod>
        <changefreq><?php echo $page['changefreq']; ?></changefreq>
        <priority><?php echo $page['priority']; ?></priority>
    </url>
<?php endforeach; ?>
<?php foreach ($events as $ev): ?>
    <url>
        <loc><?php echo htmlspecialchars($baseUrl . '/event.php?id=' . (int) $ev['id']); ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($ev['created_at'] ?? 'now')); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
<?php endforeach; ?>
</urlset>
