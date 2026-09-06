<?php

require_once __DIR__ . '/media.php';

/**
 * The real brand details. One list, used by the admin button and the command
 * line tool, so a host with no shell is not a second-class way to do this.
 */
function pmIdentityDefaults(): array
{
    return [
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
}

/**
 * Fill in the brand details and put the logo and favicon into the media
 * library. Safe to run repeatedly: the text values are rewritten, the images
 * are imported only when the slot is empty.
 *
 * SMTP settings are never touched. They hold the live mail password, and this
 * has no business rewriting it.
 *
 * @return array<int, string> A line per action, for showing back to the operator.
 */
function pmIdentitySeed(PDO $pdo, string $actor = 'admin'): array
{
    $log = [];

    try {
        $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

        foreach (pmIdentityDefaults() as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $log[] = 'Site details filled in.';
    } catch (Throwable $e) {
        error_log('identity seed: settings failed: ' . $e->getMessage());
        $log[] = 'The site details could not be saved. The error has been logged.';

        return $log;
    }

    try {
        ensureMediaSchema($pdo);

        if (!pmMediaEnsureDirs()) {
            $log[] = 'The uploads directory is not writable, so the logo and favicon were not imported.';

            return $log;
        }

        $images = [
            'site_logo'    => ['source' => 'fisrt-logo.png',  'alt' => 'Prosperminds logo',       'label' => 'Site logo'],
            'site_favicon' => ['source' => 'favicon-512.png', 'alt' => 'Prosperminds shield mark', 'label' => 'Site favicon'],
        ];

        foreach ($images as $slot => $meta) {
            $key   = $slot . '_media_id';
            $check = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
            $check->execute([$key]);

            if ((int) ($check->fetchColumn() ?: 0) > 0) {
                $log[] = ucfirst(str_replace('site_', '', $slot)) . ' was already set, left alone.';
                continue;
            }

            $source = __DIR__ . '/../assets/images/' . $meta['source'];
            if (!is_file($source)) {
                $log[] = $meta['source'] . ' is not on the server, so it was skipped.';
                continue;
            }

            $filename = pmMediaSlug(pathinfo($meta['source'], PATHINFO_FILENAME))
                      . '-' . bin2hex(random_bytes(4)) . '.png';

            if (!copy($source, pmMediaPath($filename))) {
                $log[] = $meta['source'] . ' could not be copied into the library.';
                continue;
            }

            pmMediaGenerateSizes($filename, 'image/png');
            $size = @getimagesize(pmMediaPath($filename));

            $pdo->prepare('INSERT INTO cms_media (filename, original_name, mime, bytes, width, height, alt_text, uploaded_by)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$filename, $meta['source'], 'image/png', (int) filesize($source),
                           $size ? $size[0] : null, $size ? $size[1] : null, $meta['alt'],
                           mb_substr($actor, 0, 64)]);

            $mediaId = (int) $pdo->lastInsertId();
            $stmt->execute([$key, (string) $mediaId]);
            pmMediaRecordUsage($pdo, $mediaId, 'site_setting', $slot, $meta['label']);

            $log[] = $meta['label'] . ' imported into the media library.';
        }
    } catch (Throwable $e) {
        error_log('identity seed: images failed: ' . $e->getMessage());
        $log[] = 'The logo and favicon could not be imported. The site details were still saved.';
    }

    return $log;
}
