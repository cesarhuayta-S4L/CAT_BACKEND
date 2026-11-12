<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/respuesta.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderError('Método no permitido', 405);

auth_iniciar_sesion();
$_SESSION = array();
$p = session_get_cookie_params();
setcookie(session_name(), '', array(
    'expires' => time() - 3600, 'path' => $p['path'],
    'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax',
));
session_destroy();

responder(array('authenticated' => false));
