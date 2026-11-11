<?php
/**
 * Shared security helpers for CraveLab.
 * Include after session_start() on pages that render or process forms.
 */
if (!function_exists('cravelab_csrf_token')) {
    function cravelab_csrf_token(): string {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    function cravelab_verify_csrf(): void {
        $submitted = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';

        if (!is_string($submitted) || !is_string($stored) ||
            $stored === '' || !hash_equals($stored, $submitted)) {
            http_response_code(403);
            exit('Invalid or expired request. Please go back, refresh the page, and try again.');
        }
    }

    function cravelab_escape($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
