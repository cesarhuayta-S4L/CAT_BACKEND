<?php
require_once __DIR__ . '/../config/auth.php';

function auth_iniciar_sesion() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_name(SESSION_NAME);
    ini_set('session.gc_maxlifetime', (string)SESSION_TTL);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => SESSION_COOKIE_PATH,
        'secure'   => $https,       // en localhost (http) queda en false
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

/** Devuelve el usuario en sesión o null. Libera el lock de sesión de inmediato. */
function auth_usuario() {
    auth_iniciar_sesion();
    $u = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
    if ($u && (time() - (isset($_SESSION['inicio']) ? $_SESSION['inicio'] : 0)) > SESSION_TTL) {
        $_SESSION = array();
        $u = null;
    }
    session_write_close(); // evita que las peticiones paralelas se bloqueen entre sí
    return $u;
}

/** Úsala al inicio de cada endpoint protegido. */
function require_auth() {
    $u = auth_usuario();
    if (!$u) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(array('error' => 'No autenticado'));
        exit;
    }
    return $u;
}
