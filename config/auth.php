<?php
// Client ID de Google (es público; el Client Secret YA NO se usa en este flujo).
define('GOOGLE_CLIENT_ID', '614771014095-pi8a3h7e058q0p309mihbr3pf2te6m0h.apps.googleusercontent.com');
define('ALLOWED_DOMAINS', array('s4learning.com', 'utel.edu.mx', 'scalahed.com', 'scalalearning.com'));

define('SESSION_NAME', 'PORTAL_SID');
define('SESSION_TTL', 8 * 3600);              // 8 horas
define('SESSION_COOKIE_PATH', '/portal-ejecutivo/');
