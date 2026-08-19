<?php
/**
 * Minimal .env loader.
 *
 * Deliberately dependency-free: this file is required from db-credentials.php,
 * which runs before Composer's autoloader is guaranteed to be available, and
 * pulling in vendor/vlucas/phpdotenv would mean the site cannot boot if
 * vendor/ is ever incompletely uploaded again — exactly the failure mode that
 * broke registration in August 2026.
 *
 * Resolution order for any key (first hit wins):
 *
 *   1. A real environment variable — $_SERVER, then $_ENV, then getenv().
 *      This is the preferred way to supply secrets in production (cPanel's
 *      "Environment Variables" / MultiPHP INI, or SetEnv in the vhost).
 *   2. A KEY=VALUE line in a .env file. Candidates, in order:
 *        a) the absolute path in the PM_ENV_FILE environment variable, if set
 *        b) <document root>/.env
 *      PM_ENV_FILE exists so the file can live outside the web root. Note that
 *      the main site and the CPD subdomain use the SAME variable names
 *      (DB_HOST, DB_USER, ...), so they must NOT share one .env file — give
 *      each site its own, or point each at a different path via PM_ENV_FILE.
 *   3. The $default argument.
 *
 * A .env file inside the document root is additionally blocked from the web by
 * the "deny dotfiles" rule in public_html/.htaccess. Keep it chmod 600.
 *
 * Value parsing is intentionally literal: everything after the first "=" is
 * the value, with only a matched pair of surrounding quotes stripped. There is
 * no inline-comment stripping and no "$VAR" interpolation, because real
 * credentials here contain #, $, { and } and must survive verbatim.
 */

/**
 * Read a raw value from the process environment only (no .env fallback).
 */
function pm_env_from_process(string $key): ?string
{
    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }

    return null;
}

/**
 * Parse a .env file into a key => value map. Returns [] if unreadable.
 */
function pm_env_parse_file(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $values[$key] = $value;
    }

    return $values;
}

/**
 * Load the .env file once per request. Safe to call repeatedly.
 */
function pm_env_load(): array
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    $candidates = [];

    $explicit = pm_env_from_process('PM_ENV_FILE');
    if ($explicit !== null) {
        $candidates[] = $explicit;
    }

    // includes/ sits directly inside the document root.
    $candidates[] = dirname(__DIR__) . '/.env';

    foreach ($candidates as $candidate) {
        $values = pm_env_parse_file($candidate);
        if ($values !== []) {
            return $loaded = $values;
        }
    }

    return $loaded = [];
}

/**
 * Resolve a configuration value. Process environment beats .env beats default.
 */
function pm_env(string $key, ?string $default = null): ?string
{
    $value = pm_env_from_process($key);
    if ($value !== null) {
        return $value;
    }

    $fileValues = pm_env_load();
    if (array_key_exists($key, $fileValues) && $fileValues[$key] !== '') {
        return $fileValues[$key];
    }

    return $default;
}

/**
 * Resolve a value that the site cannot run without.
 *
 * Fails loudly in the log and generically to the visitor, rather than falling
 * back to a wrong value and producing a confusing downstream error.
 */
function pm_env_required(string $key): string
{
    $value = pm_env($key);
    if ($value === null || $value === '') {
        error_log(
            "Configuration error: required environment variable {$key} is not set. "
            . 'Set it in the server environment or in a .env file (see .env.example).'
        );
        http_response_code(500);
        die('Site configuration is incomplete. Please contact the administrator.');
    }

    return $value;
}
