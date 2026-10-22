<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/lib/respuesta.php';
require_once __DIR__ . '/lib/cac.php';
require_once __DIR__ . '/lib/indicadores.php';

$pais  = param('pais', 'PERU');
$desde = param('desde', date('Y-m-01'));
$hasta = param('hasta', date('Y-m-d'));
validarRango($desde, $hasta);

$totales = obtenerTotales($pais, $desde, $hasta);
if ($totales === false) responderError('Error al consultar', 500);

$indicadores = array();
foreach ($totales as $archivo => $t) {
    $indicadores[$archivo] = calcularIndicadores($t);
}

responder(array(
    'pais'        => $pais,
    'desde'       => $desde,
    'hasta'       => $hasta,
    'indicadores' => (object)$indicadores
));