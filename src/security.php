<?php
// security.php — Funciones centrales de seguridad para Vaulta
// Incluir DESPUÉS de session_start()

/**
 * Genera (o reutiliza) el token CSRF de la sesión actual.
 * @return string Token hexadecimal de 64 caracteres.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Devuelve un campo hidden HTML listo para insertar en cualquier formulario.
 * @return string  <input type="hidden" name="csrf_token" value="...">
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida el token CSRF enviado en el POST.
 * Si no es válido, aborta la ejecución con error 403.
 */
function validate_csrf_token(): void {
    $token_recibido  = $_POST['csrf_token'] ?? '';
    $token_sesion    = $_SESSION['csrf_token'] ?? '';

    if (!$token_sesion || !hash_equals($token_sesion, $token_recibido)) {
        // Redirect back to the referring page with an error flag
        $referrer = $_SERVER['HTTP_REFERER'] ?? 'login.php';
        // Only redirect to local pages for safety
        $parsed = parse_url($referrer);
        $safe_referrer = (isset($parsed['host']) && $parsed['host'] !== $_SERVER['HTTP_HOST'])
            ? 'login.php'
            : $referrer;
        header('Location: ' . $safe_referrer . (strpos($safe_referrer, '?') !== false ? '&' : '?') . 'csrf_error=1');
        exit;
    }
}

/**
 * Escapa una cadena para imprimirla de forma segura en HTML (evita XSS).
 * @param mixed $str Valor a escapar.
 * @return string Cadena escapada.
 */
function esc($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
/**
 * Returns a non-empty error string if the current request has ?csrf_error=1 in the URL.
 * Use this to show a user-friendly CSRF error message in form pages.
 * @return string  Error message, or empty string if no CSRF error.
 */
function csrf_error_message(): string {
    if (!empty($_GET['csrf_error'])) {
        return 'Tu sesión ha caducado o la solicitud no es válida. Por favor, inténtalo de nuevo.';
    }
    return '';
}
?>
