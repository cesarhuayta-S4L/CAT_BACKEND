<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/lib/respuesta.php';
require_once __DIR__ . '/lib/cac.php';

$pais  = param('pais', 'PERU');
$desde = param('desde', date('Y-m-01'));
$hasta = param('hasta', date('Y-m-d'));
validarRango($desde, $hasta);

$serie = obtenerLeadsDiarios($pais, $desde, $hasta);
if ($serie === false) responderError('Error al consultar', 500);

responder(array(
    'pais'  => $pais,
    'desde' => $desde,
    'hasta' => $hasta,
    'serie' => (object)$serie
));