<?php
/**
 * Command line wrapper. The same job is a button in Settings, which is what a
 * cPanel account with no shell uses.
 *
 *   php tools/seed-site-identity.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/identity.php';

foreach (pmIdentitySeed($pdo, 'cli') as $line) {
    echo $line, PHP_EOL;
}
