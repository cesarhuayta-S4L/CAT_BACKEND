<?php
// Siempre responde 200: indica si hay sesión activa o no.
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/respuesta.php';

header('Cache-Control: no-store');
$u = auth_usuario();
responder($u ? array('authenticated' => true, 'user' => $u) : array('authenticated' => false));
