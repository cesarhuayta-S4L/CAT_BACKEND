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

// Bloques del periodo pedido (desde-hasta), completados con BUDGET donde falta dato:
// ESTIMADO = real hasta ayer + budget del resto del rango; FORECAST_PERIODO = forecast del rango.
$estimado = obtenerTotalesEstimado($pais, $desde, $hasta, date('Y-m-d'));
if ($estimado === false) responderError('Error al consultar', 500);
$indicadores['ESTIMADO'] = calcularIndicadores($estimado);

// Si no hay datos de forecast para ese país/rango, no se devuelve el bloque.
$forecast = obtenerTotalesForecast($pais, $desde, $hasta);
if ($forecast === false) responderError('Error al consultar', 500);
if (count($forecast) > 0) $indicadores['FORECAST_PERIODO'] = calcularIndicadores($forecast);

responder(array(
    'pais'        => $pais,
    'desde'       => $desde,
    'hasta'       => $hasta,
    'indicadores' => (object)$indicadores
));