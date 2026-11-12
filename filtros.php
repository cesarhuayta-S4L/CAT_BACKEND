<?php
require_once __DIR__ . '/lib/auth.php';
require_auth();
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/lib/respuesta.php';
require_once __DIR__ . '/lib/cac.php';

responder(obtenerFiltros());