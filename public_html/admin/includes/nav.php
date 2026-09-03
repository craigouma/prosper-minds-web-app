<?php
function pmAdminIcons(): array {
    return [
        'overview'      => 'M2.5 2.5h4.5v4.5H2.5zM9 2.5h4.5v4.5H9zM2.5 9h4.5v4.5H2.5zM9 9h4.5v4.5H9z',
        'analytics'     => 'M3 13V8M8 13V3.5M13 13v-3.5',
        'registrations' => 'M3 4h10M3 8h10M3 12h6',
        'accounting'    => 'M4 2.5h8v11l-2-1.2-2 1.2-2-1.2-2 1.2zM6 6h4M6 9h4',
        'events'        => 'M2.5 4h11v9.5h-11zM2.5 7h11M5.5 2.5v3M10.5 2.5v3',
        'eventeditor'   => 'M3 3h10v3H3zM3 8h10v2H3zM3 11.5h6v2H3z',
        'earlybird'     => 'M8 3a5 5 0 110 10A5 5 0 018 3zM8 5.5V8l2 1.5',
        'banners'       => 'M3 4h10v8l-5-3-5 3z',
        'pages'         => 'M4 2h5l3 3v9H4zM9 2v3h3M6 8h4M6 11h3',
        'media'         => 'M2.5 3.5h11v9h-11zM2.5 10l3-3 2.5 2.5 2-2 3.5 3.5',
        'menus'         => 'M3 4h10M3 8h7M6 12h7',
        'submissions'   => 'M2.5 8.5 4.5 4h7l2 4.5v4h-11zM2.5 8.5h3l1 1.5h3l1-1.5h3',
        'revisions'     => 'M8 3a5 5 0 110 10A5 5 0 018 3zM8 5.5V8l2 1.5',
        'seo'           => 'M7.2 3a4.2 4.2 0 110 8.4 4.2 4.2 0 010-8.4zM10.4 10.4 13.5 13.5',
        'templates'     => 'M3 3h4.5v4.5H3zM8.5 3H13v4.5H8.5zM3 8.5h4.5V13H3z',
        'users'         => 'M8 3.2a2.2 2.2 0 110 4.4 2.2 2.2 0 010-4.4zM3.5 13.2c0-2.3 2-3.6 4.5-3.6s4.5 1.3 4.5 3.6',
        'settings'      => 'M3 5h4M11 5h2M3 11h2M9 11h4M9 3.5v3M7 9.5v3',
        'redirects'     => 'M3 5.5h8l-2-2M13 10.5H5l2 2',
        'audit'         => 'M8 2.5 13 4.3v4.2c0 3-2.2 5-5 6-2.8-1-5-3-5-6V4.3z',
        'health'        => 'M2.5 8h3l1.5-3.5L9 11.5l1.5-3.5h3',
        'trash'         => 'M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.7 9h5.6l.7-9M6.8 7v4M9.2 7v4',
    ];
}

function pmAdminNav(): array {
    return [
        ['label' => 'Operations', 'items' => [
            ['key' => 'dashboard',     'label' => 'Overview',      'href' => 'dashboard.php',     'module' => 'dashboard',     'crumb' => 'Dashboard',            'built' => true],
            ['key' => 'analytics',     'label' => 'Analytics',     'href' => 'analytics.php',     'module' => 'dashboard',     'crumb' => 'Dashboard',            'built' => true, 'icon' => 'analytics'],
            ['key' => 'registrations', 'label' => 'Registrations', 'href' => 'registrations.php', 'module' => 'registrations', 'crumb' => 'Delegate operations',  'built' => true],
            ['key' => 'accounting',    'label' => 'Accounting',    'href' => 'accounting.php',    'module' => 'accounting',    'crumb' => 'Finance',              'built' => true, 'superOnly' => true],
        ]],
        ['label' => 'Programme', 'items' => [
            ['key' => 'events',    'label' => 'Events',             'href' => 'events.php',    'module' => 'events',    'crumb' => 'Programme', 'built' => true],
            ['key' => 'earlybird', 'label' => 'Early bird control', 'href' => 'earlybird.php', 'module' => 'events',    'crumb' => 'Programme', 'built' => false],
            ['key' => 'banners',   'label' => 'Banner library',     'href' => 'banners.php',   'module' => 'media',     'crumb' => 'Programme', 'built' => false],
        ]],
        ['label' => 'Content', 'items' => [
            ['key' => 'pages',       'label' => 'Pages',           'href' => 'pages.php',       'module' => 'content',     'crumb' => 'Content', 'built' => true],
            ['key' => 'media',       'label' => 'Media library',   'href' => 'media.php',       'module' => 'media',       'crumb' => 'Content', 'built' => true],
            ['key' => 'menus',       'label' => 'Menus',           'href' => 'menus.php',       'module' => 'menus',       'crumb' => 'Content', 'built' => true],
            ['key' => 'submissions', 'label' => 'Submissions',     'href' => 'submissions.php', 'module' => 'submissions', 'crumb' => 'Content', 'built' => true],
            ['key' => 'seo',         'label' => 'SEO and schema',  'href' => 'seo.php',         'module' => 'seo',         'crumb' => 'Content', 'built' => false],
            ['key' => 'trash',       'label' => 'Trash',           'href' => 'trash.php',       'module' => 'content',     'crumb' => 'Content', 'built' => true],
            ['key' => 'templates',   'label' => 'Reusable blocks', 'href' => '#',               'module' => 'content',     'crumb' => 'Content', 'built' => false, 'soon' => true],
        ]],
        ['label' => 'System', 'items' => [
            ['key' => 'users',     'label' => 'Users',              'href' => 'users.php',     'module' => 'users',     'crumb' => 'System', 'built' => true, 'superOnly' => true],
            ['key' => 'settings',  'label' => 'Settings',           'href' => 'settings.php',  'module' => 'settings',  'crumb' => 'System', 'built' => true, 'superOnly' => true],
            ['key' => 'redirects', 'label' => 'Redirects and 404s', 'href' => 'redirects.php', 'module' => 'redirects', 'crumb' => 'System', 'built' => false],
            ['key' => 'audit',     'label' => 'Audit log',          'href' => 'audit.php',     'module' => 'audit',     'crumb' => 'System', 'built' => false, 'superOnly' => true],
            ['key' => 'health',    'label' => 'Site health',        'href' => 'health.php',    'module' => 'health',    'crumb' => 'System', 'built' => false],
        ]],
    ];
}

function pmAdminScreen(string $key): ?array {
    foreach (pmAdminNav() as $group) {
        foreach ($group['items'] as $item) {
            if ($item['key'] === $key) {
                return $item + ['group' => $group['label']];
            }
        }
    }
    return null;
}

function pmAdminCanSee(array $item): bool {
    if (!empty($item['superOnly']) && !isSuper()) {
        return false;
    }
    return hasPermission($item['module'], 'view');
}
