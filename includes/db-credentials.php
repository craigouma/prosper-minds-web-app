<?php
// Database credentials.
//
// These used to be hardcoded here in plaintext and were tracked in git. They
// are now read from the environment: a real environment variable if the server
// sets one, otherwise a KEY=VALUE line in a .env file that is NOT tracked in
// git. See .env.example for the full list and includes/env.php for the
// resolution order.
//
// DEPLOYMENT WARNING: this file no longer contains any fallback credentials.
// The environment variables (or a .env file) MUST exist on the server before
// this code is deployed, or every page will stop at "Site configuration is
// incomplete."

require_once __DIR__ . '/env.php';

define('DB_HOST', pm_env('DB_HOST', 'localhost'));
define('DB_USER', pm_env_required('DB_USER'));
define('DB_PASS', pm_env_required('DB_PASS'));
define('DB_NAME', pm_env_required('DB_NAME'));
define('DB_PORT', pm_env('DB_PORT', '3306'));
