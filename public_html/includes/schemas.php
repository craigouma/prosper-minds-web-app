<?php

/**
 * Create every table the system needs, in one call.
 *
 * Each screen already creates its own storage the first time it loads, which
 * is what keeps a deploy from needing a migration runner on a host with no
 * shell. That is fine day to day and confusing during a deployment: it leaves
 * the operator clicking through screens to make tables appear.
 *
 * This does the same work deliberately, so Site health can set everything up
 * and then report what exists.
 *
 * @return array<string, bool> Table name to whether it is now present.
 */
function pmEnsureAllSchemas(PDO $pdo): array
{
    $files = ['config', 'invoice', 'funnel', 'content', 'contact', 'newsletter', 'sponsorship',
              'audit', 'media', 'menus', 'pages', 'trash', 'redirects', 'testimonials',
              'adminsession', 'accounting', 'resume'];

    foreach ($files as $file) {
        $path = __DIR__ . '/' . $file . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }

    $callers = ['ensureFailedNotificationSchema', 'ensureRegistrationInvoiceSchema',
                'ensureFunnelEventsSchema', 'ensurePageContentSchema', 'ensureContactMessageSchema',
                'ensureNewsletterSubscriberSchema', 'ensureSponsorshipEnquirySchema',
                'pmAuditEnsureSchema', 'ensureMediaSchema', 'ensureMenuSchema', 'ensurePagesSchema',
                'ensureTrashSchema', 'ensureRedirectSchema', 'ensureTestimonialSchema',
                'ensureAdminSessionSchema', 'ensureAccountingSchema', 'ensureResumeSchema'];

    foreach ($callers as $fn) {
        if (!function_exists($fn)) {
            continue;
        }
        try {
            $fn($pdo);
        } catch (Throwable $e) {
            // One table failing must not stop the rest being created.
            error_log('schema setup: ' . $fn . ' failed: ' . $e->getMessage());
        }
    }

    return pmSchemaState($pdo);
}

/** @return array<string, bool> */
function pmSchemaState(PDO $pdo): array
{
    $expected = [
        'page_content', 'contact_messages', 'newsletter_subscribers', 'sponsorship_enquiries',
        'failed_notifications', 'funnel_events', 'cms_audit_log', 'cms_media', 'cms_media_usage',
        'cms_menu_items', 'cms_pages', 'cms_page_blocks', 'cms_revisions', 'cms_preview_tokens',
        'cms_trash', 'cms_redirects', 'cms_not_found', 'cms_testimonials',
        'admin_remember_tokens', 'admin_password_resets',
        'registration_resumes', 'registration_reminder_optouts',
    ];

    $present = [];
    try {
        $rows = $pdo->query("SELECT table_name FROM information_schema.tables
                              WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
        $have  = array_map('strtolower', $rows ?: []);
    } catch (Throwable $e) {
        error_log('schema state: could not list tables: ' . $e->getMessage());
        $have = [];
    }

    foreach ($expected as $table) {
        $present[$table] = in_array(strtolower($table), $have, true);
    }

    return $present;
}
