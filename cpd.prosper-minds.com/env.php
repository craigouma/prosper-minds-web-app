<?php
/**
 * Minimal .env loader for the CPD subdomain.
 *
 * Deliberately dependency-free — it must work even if vendor/ is ever
 * incompletely uploaded, which is exactly what took the main site's
 * registration down in August 2026.
 *
 * Resolution order for any key (first hit wins):
 *
 *   1. A real environment variable — $_SERVER, then $_ENV, then getenv().
 *      Preferred in production (cPanel "Environment Variables", or SetEnv).
 *   2. A KEY=VALUE line in a .env file. Candidates, in order:
 *        a) the absolute path in the CPD_ENV_FILE environment variable, if set
 *        b) <document root>/.env
 *      CPD_ENV_FILE exists so the file can live outside the web root. Note that
 *      the main site uses the SAME variable names (DB_HOST, DB_USER, ...) with
 *      DIFFERENT values, so the two sites must never share one .env file.
 *   3. The $default argument.
 *
 * A .env file inside the document root is additionally blocked from the web by
 * the "deny dotfiles" rule in .htaccess. Keep it chmod 600.
 *
 * Value parsing is intentionally literal: everything after the first "=" is
 * the value, with only a matched pair of surrounding quotes stripped. There is
 * no inline-comment stripping and no "$VAR" interpolation, so credentials
 * containing # $ { } survive verbatim.
 */

function cpd_env_from_process(string $key): ?string
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

function cpd_env_parse_file(string $path): array
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

function cpd_env_load(): array
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    $candidates = [];

    $explicit = cpd_env_from_process('CPD_ENV_FILE');
    if ($explicit !== null) {
        $candidates[] = $explicit;
    }

    $candidates[] = __DIR__ . '/.env';

    foreach ($candidates as $candidate) {
        $values = cpd_env_parse_file($candidate);
        if ($values !== []) {
            return $loaded = $values;
        }
    }

    return $loaded = [];
}

function cpd_env(string $key, ?string $default = null): ?string
{
    $value = cpd_env_from_process($key);
    if ($value !== null) {
        return $value;
    }

    $fileValues = cpd_env_load();
    if (array_key_exists($key, $fileValues) && $fileValues[$key] !== '') {
        return $fileValues[$key];
    }

    return $default;
}

/**
 * Resolve a value the site cannot run without. Fails loudly in the log and
 * generically to the visitor.
 */
function cpd_env_required(string $key): string
{
    $value = cpd_env($key);
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
