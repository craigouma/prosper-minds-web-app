<?php
/**
 * Retired. The schools are on the home page and past cohorts are on About. A permanent redirect keeps old links and search results working.
 */

if (($_GET['show'] ?? '') === 'past') {
    header('Location: /about.php#past-cohorts', true, 301);
    exit;
}

header('Location: /#schools', true, 301);
exit;
