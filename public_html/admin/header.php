<?php
require_once __DIR__ . '/includes/nav.php';

$pmScreenKey = $activePage ?? 'dashboard';
$pmScreen    = pmAdminScreen($pmScreenKey);
$pmTitle     = $pageTitle ?? ($pmScreen['label'] ?? 'Dashboard');
$pmCrumb     = $pmScreen['crumb'] ?? '';
$pmIcons     = pmAdminIcons();

$pmUser      = (string) ($_SESSION['admin_username'] ?? 'Admin');
$pmFullName  = trim((string) ($_SESSION['admin_full_name'] ?? '')) ?: $pmUser;
$pmInitials  = '';
foreach (preg_split('/\s+/', $pmFullName) as $pmPart) {
    if ($pmPart !== '') {
        $pmInitials .= strtoupper(substr($pmPart, 0, 1));
    }
}
$pmInitials = substr($pmInitials, 0, 2) ?: strtoupper(substr($pmUser, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($pmTitle); ?> | Prosperminds Admin</title>
    <link rel="icon" href="../assets/images/favicon-32.png" sizes="32x32">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/pm-admin.css">
    <script src="../assets/js/pm-admin.js" defer></script>
</head>
<body class="pma">
<div class="pma-shell">

    <aside class="pma-side">
        <a class="pma-brand" href="dashboard.php">
            <img src="../assets/images/favicon-512.png" alt="" width="20" height="21">
            <span>Prosperminds</span>
        </a>

<?php foreach (pmAdminNav() as $pmGroup):
        $pmVisible = array_filter($pmGroup['items'], static function (array $item): bool {
            return !empty($item['built']) || !empty($item['soon']);
        });
        if (!$pmVisible) {
            continue;
        }
?>
        <div class="pma-navgroup">
            <div class="pma-navgroup-label"><?php echo htmlspecialchars($pmGroup['label']); ?></div>
            <nav class="pma-nav">
<?php   foreach ($pmVisible as $pmItem):
            $pmKey     = $pmItem['key'];
            $pmAllowed = pmAdminCanSee($pmItem);
            $pmLocked  = !$pmAllowed || !empty($pmItem['soon']);
            $pmPath    = $pmIcons[$pmItem['icon'] ?? $pmKey] ?? $pmIcons['pages'];
            $pmClasses = 'pma-nav-item'
                . ($pmKey === $pmScreenKey ? ' is-active' : '')
                . ($pmLocked ? ' is-locked' : '');
            $pmTag     = $pmLocked ? 'span' : 'a';
?>
                <<?php echo $pmTag; ?> class="<?php echo $pmClasses; ?>"
<?php       if (!$pmLocked): ?> href="<?php echo htmlspecialchars($pmItem['href']); ?>"<?php endif; ?>
<?php       if ($pmLocked && !$pmAllowed): ?> title="Not part of your permissions"<?php endif; ?>>
                    <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="<?php echo $pmPath; ?>" fill="none" stroke="currentColor"
                              stroke-width="1.3" stroke-linejoin="round"></path>
                    </svg>
                    <span><?php echo htmlspecialchars($pmItem['label']); ?></span>
<?php       if (!empty($pmItem['soon'])): ?>
                    <span class="pma-chip-later">Later</span>
<?php       elseif (!$pmAllowed): ?>
                    <svg width="12" height="12" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M4 7.5h8v6H4zM5.8 7.5V5.2a2.2 2.2 0 014.4 0v2.3" fill="none"
                              stroke="currentColor" stroke-width="1.3"></path>
                    </svg>
<?php       endif; ?>
                </<?php echo $pmTag; ?>>
<?php   endforeach; ?>
            </nav>
        </div>
<?php endforeach; ?>

        <div class="pma-side-foot">
            <div class="pma-side-user">
                <span class="pma-avatar"><?php echo htmlspecialchars($pmInitials); ?></span>
                <div>
                    <div class="pma-side-user-name"><?php echo htmlspecialchars($pmUser); ?></div>
                    <div class="pma-side-user-role"><?php echo isSuper() ? 'Super admin' : 'Editor'; ?></div>
                </div>
            </div>
            <a class="pma-signout" href="logout.php">Sign out</a>
        </div>
    </aside>

    <main class="pma-main">
        <header class="pma-top">
          <div class="pma-top-inner">
            <button type="button" class="pma-side-toggle" aria-expanded="true"
                    aria-label="Show or hide the sidebar">
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M2.5 4.5h11M2.5 8h11M2.5 11.5h11" fill="none"
                          stroke="currentColor" stroke-width="1.4"></path>
                </svg>
            </button>
            <div style="min-width:0">
<?php if ($pmCrumb !== ''): ?>
                <div class="pma-crumb"><?php echo htmlspecialchars($pmCrumb); ?></div>
<?php endif; ?>
                <h1><?php echo htmlspecialchars($pmTitle); ?></h1>
            </div>
            <div class="pma-top-actions">
                <button type="button" class="pma-search-trigger" data-palette-open>
                    <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M7.2 3a4.2 4.2 0 110 8.4 4.2 4.2 0 010-8.4zM10.4 10.4 13.5 13.5"
                              fill="none" stroke="currentColor" stroke-width="1.3"></path>
                    </svg>
                    <span>Search pages, events, delegates</span>
                    <kbd>Ctrl K</kbd>
                </button>
                <a href="../index.php" target="_blank" rel="noopener" class="btn btn-outline btn-sm">View site</a>
            </div>
          </div>
        </header>

        <div class="pma-palette" id="pm-palette" hidden>
          <div class="pma-palette-panel" role="dialog" aria-modal="true" aria-label="Search the panel">
            <label class="pma-palette-field">
              <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M7.2 3a4.2 4.2 0 110 8.4 4.2 4.2 0 010-8.4zM10.4 10.4 13.5 13.5"
                      fill="none" stroke="#6b6b6b" stroke-width="1.3"></path>
              </svg>
              <span class="pma-vh">Search the panel</span>
              <input type="text" data-palette-input autocomplete="off"
                     placeholder="Screens, delegates, events, pages, files, enquiries">
            </label>
            <div class="pma-palette-status" data-palette-status role="status"></div>
            <div class="pma-palette-results" data-palette-results></div>
          </div>
        </div>

        <div class="pma-body">
<?php if (!empty($_SESSION['perm_error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['perm_error']); unset($_SESSION['perm_error']); ?>
            </div>
<?php endif; ?>
