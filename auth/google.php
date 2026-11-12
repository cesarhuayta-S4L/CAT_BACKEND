<?php
// POST { "credential": "<ID token de Google>" }
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/respuesta.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderError('Método no permitido', 405);

$body = json_decode(file_get_contents('php://input'), true);
$credential = (is_array($body) && isset($body['credential'])) ? $body['credential'] : '';
if (!is_string($credential) || $credential === '' || strlen($credential) > 4096) {
    responderError('Credencial inválida', 400);
}

// Google valida firma y expiración del token.
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) responderError('No se pudo conectar con Google. Intenta de nuevo.', 502);
if ($http !== 200)   responderError('El token de Google no es válido o expiró.', 401);

$info = json_decode($resp, true);
if (!is_array($info)) responderError('Respuesta inesperada de Google.', 502);

$aud = isset($info['aud']) ? $info['aud'] : '';
$iss = isset($info['iss']) ? $info['iss'] : '';
$exp = isset($info['exp']) ? (int)$info['exp'] : 0;
$email = isset($info['email']) ? strtolower($info['email']) : '';
$verificado = isset($info['email_verified']) && ($info['email_verified'] === true || $info['email_verified'] === 'true');

if (!hash_equals(GOOGLE_CLIENT_ID, (string)$aud))                                   responderError('Token emitido para otra aplicación.', 401);
if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com')        responderError('Emisor de token inválido.', 401);
if ($exp < time())                                                                  responderError('El token expiró.', 401);
if ($email === '' || !$verificado)                                                  responderError('No se pudo verificar el correo de la cuenta.', 401);

$partes = explode('@', $email);
$dominio = end($partes);
if (!in_array($dominio, ALLOWED_DOMAINS, true)) {
    responderError('Tu cuenta (' . $email . ') no pertenece a un dominio autorizado. Usa tu correo corporativo.', 403);
}

auth_iniciar_sesion();
session_regenerate_id(true);
$usuario = array(
    'email'   => $email,
    'name'    => isset($info['name']) ? $info['name'] : $email,
    'picture' => isset($info['picture']) ? $info['picture'] : '',
);
$_SESSION['usuario'] = $usuario;
$_SESSION['inicio']  = time();
session_write_close();

responder(array('authenticated' => true, 'user' => $usuario));
