<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/media.php';
require_once '../includes/pages.php';
require_once 'includes/nav.php';
requireAdminAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');

$q = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like    = '%' . $q . '%';
$results = [];

/**
 * Every query below is wrapped, because search is a convenience and a missing
 * table in one section must not empty the whole palette.
 */
function pmSearchTry(callable $probe, array &$results): void
{
    try {
        foreach ($probe() as $row) {
            $results[] = $row;
        }
    } catch (Throwable $e) {
        error_log('admin search: a section failed: ' . $e->getMessage());
    }
}

// Screens first: navigating is the most common reason to open this.
foreach (pmAdminNav() as $group) {
    foreach ($group['items'] as $item) {
        if (empty($item['built']) || !pmAdminCanSee($item)) {
            continue;
        }
        if (stripos($item['label'], $q) !== false || stripos($group['label'], $q) !== false) {
            $results[] = ['kind' => 'Screen', 'title' => $item['label'],
                          'detail' => $group['label'], 'href' => $item['href']];
        }
    }
}

if (hasPermission('registrations', 'view')) {
    pmSearchTry(static function () use ($pdo, $like) {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, organization, invoice_number
                                 FROM event_registrations
                                WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                                   OR organization LIKE ? OR invoice_number LIKE ?
                                ORDER BY id DESC LIMIT 6");
        $stmt->execute([$like, $like, $like, $like, $like]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = ['kind' => 'Delegate',
                      'title' => trim($r['first_name'] . ' ' . $r['last_name']),
                      'detail' => trim(($r['organization'] ?: $r['email']) . ' ' . ($r['invoice_number'] ?? '')),
                      'href' => 'registrations.php?q=' . rawurlencode($r['email'])];
        }

        return $out;
    }, $results);
}

if (hasPermission('events', 'view')) {
    pmSearchTry(static function () use ($pdo, $like) {
        $stmt = $pdo->prepare('SELECT id, title, date_display, location FROM events
                                WHERE title LIKE ? OR location LIKE ? ORDER BY sort_order LIMIT 6');
        $stmt->execute([$like, $like]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = ['kind' => 'Event', 'title' => $r['title'],
                      'detail' => trim($r['date_display'] . ', ' . $r['location']),
                      'href' => 'events.php?edit=' . (int) $r['id']];
        }

        return $out;
    }, $results);
}

if (hasPermission('content', 'view')) {
    pmSearchTry(static function () use ($pdo, $like) {
        $stmt = $pdo->prepare('SELECT id, title, slug, status FROM cms_pages
                                WHERE (title LIKE ? OR slug LIKE ?) AND trashed_at IS NULL
                                ORDER BY id DESC LIMIT 6');
        $stmt->execute([$like, $like]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = ['kind' => 'Page', 'title' => $r['title'],
                      'detail' => '/' . $r['slug'] . ', ' . $r['status'],
                      'href' => 'page-editor.php?id=' . (int) $r['id']];
        }

        return $out;
    }, $results);
}

if (hasPermission('media', 'view')) {
    pmSearchTry(static function () use ($pdo, $like) {
        $stmt = $pdo->prepare('SELECT id, original_name, alt_text FROM cms_media
                                WHERE original_name LIKE ? OR alt_text LIKE ? ORDER BY id DESC LIMIT 5');
        $stmt->execute([$like, $like]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = ['kind' => 'File', 'title' => $r['original_name'],
                      'detail' => (string) $r['alt_text'],
                      'href' => 'media.php?id=' . (int) $r['id']];
        }

        return $out;
    }, $results);
}

if (hasPermission('submissions', 'view')) {
    pmSearchTry(static function () use ($pdo, $like, $q) {
        $stmt = $pdo->prepare('SELECT id, name, email, organisation FROM contact_messages
                                WHERE name LIKE ? OR email LIKE ? OR organisation LIKE ?
                                ORDER BY id DESC LIMIT 4');
        $stmt->execute([$like, $like, $like]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = ['kind' => 'Enquiry', 'title' => $r['name'],
                      'detail' => trim(($r['organisation'] ?: '') . ' ' . $r['email']),
                      'href' => 'submissions.php?tab=enquiries&q=' . rawurlencode($q)];
        }

        return $out;
    }, $results);
}

echo json_encode(['results' => array_slice($results, 0, 24)],
                 JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
