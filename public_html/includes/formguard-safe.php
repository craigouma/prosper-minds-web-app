<?php
/**
 * Loads the form guard without letting it take a page or a handler down.
 *
 * The guard is a secondary concern: a registration must go through even if
 * formguard.php is missing or broken (a ParseError is an Error, not an
 * Exception, so it is caught as Throwable). If it cannot load, the stand-ins
 * below let every submission through and draw no hidden fields.
 */

if (!function_exists('pmGuardCheck') && is_file(__DIR__ . '/formguard.php')) {
    try {
        require_once __DIR__ . '/formguard.php';
    } catch (Throwable $pmGuardLoadError) {
        error_log('Form guard unavailable, submissions will not be checked: ' . $pmGuardLoadError->getMessage());
    }
}

if (!function_exists('pmGuardCheck')) {
    function pmGuardCheck(PDO $pdo, string $form, array $post, array $text = [], string $who = '', string $extra = ''): array
    {
        return ['ok' => true, 'severity' => '', 'reasons' => [], 'message' => ''];
    }
}

if (!function_exists('pmGuardFields')) {
    function pmGuardFields(string $form): string
    {
        return '';
    }
}

if (!function_exists('pmGuardStatusFor')) {
    function pmGuardStatusFor(array $reasons): string
    {
        return 'invalid';
    }
}
