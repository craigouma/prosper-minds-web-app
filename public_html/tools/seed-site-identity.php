<?php
/**
 * Fill site_settings with the real brand details, and put the logo and favicon
 * into the media library so Settings shows them as set.
 *
 * Safe to run more than once: text settings are overwritten with the values
 * below, and the images are only imported when the slot is empty.
 *
 *   php tools/seed-site-identity.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/media.php';

$settings = [
    'site_title'      => 'Prosperminds',
    'site_tagline'    => 'Public finance training for the people who sign the accounts',
    'company_name'    => 'Prosperminds',
    'contact_email'   => 'info@prosper-minds.com',
    'admin_email'     => 'info@prosper-minds.com',
    'contact_phone'   => '+254 740 582302',
    'contact_address' => 'Nairobi, Kenya',
    'social_linkedin' => 'https://www.linkedin.com/company/prosper-minds-technologies/',
    'social_facebook' => 'https://www.facebook.com/share/1EvKA1GF5w/?mibextid=wwXIfr',
    'company_color'   => '#00BF63',
];

$stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

foreach ($settings as $key => $value) {
    $stmt->execute([$key, $value]);
    echo "set  $key = $value\n";
}

ensureMediaSchema($pdo);
pmMediaEnsureDirs();

$images = [
    'site_logo'    => ['source' => 'fisrt-logo.png',   'alt' => 'Prosperminds logo', 'label' => 'Site logo'],
    'site_favicon' => ['source' => 'favicon-512.png',  'alt' => 'Prosperminds shield mark', 'label' => 'Site favicon'],
];

foreach ($images as $slot => $meta) {
    $key = $slot . '_media_id';

    $existing = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
    $existing->execute([$key]);
    if ((int) ($existing->fetchColumn() ?: 0) > 0) {
        echo "skip $slot, already set\n";
        continue;
    }

    $source = __DIR__ . '/../assets/images/' . $meta['source'];
    if (!is_file($source)) {
        echo "MISS $slot, {$meta['source']} not found\n";
        continue;
    }

    $filename = pmMediaSlug(pathinfo($meta['source'], PATHINFO_FILENAME)) . '-' . bin2hex(random_bytes(4)) . '.png';
    copy($source, pmMediaPath($filename));
    pmMediaGenerateSizes($filename, 'image/png');

    $size = @getimagesize(pmMediaPath($filename));

    $pdo->prepare('INSERT INTO cms_media (filename, original_name, mime, bytes, width, height, alt_text, uploaded_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$filename, $meta['source'], 'image/png', filesize($source),
                   $size ? $size[0] : null, $size ? $size[1] : null, $meta['alt'], 'seed']);

    $mediaId = (int) $pdo->lastInsertId();
    $stmt->execute([$key, (string) $mediaId]);
    pmMediaRecordUsage($pdo, $mediaId, 'site_setting', $slot, $meta['label']);

    echo "set  $slot -> media #$mediaId ($filename)\n";
}

echo "done\n";
