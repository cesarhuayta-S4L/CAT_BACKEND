<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/lib/respuesta.php';
require_once __DIR__ . '/lib/cac.php';
require_once __DIR__ . '/lib/indicadores.php';

$pais  = param('pais', 'PERU');
$desde = param('desde', date('Y-m-01'));
$hasta = param('hasta', date('Y-m-d'));
validarRango($desde, $hasta);

$paises_activos = obtenerPaisesActivos($pais, $desde, $hasta);
if (empty($paises_activos)) responderError('No hay datos para el país o rango', 404);

$ind_archivos = array();
$ind_estimado = array();
$ind_forecast = array();
$ind_aa       = array();
$hoy = date('Y-m-d');

foreach ($paises_activos as $p) {
    
    $totales = obtenerTotales($p, $desde, $hasta);
    if ($totales !== false) {
        foreach ($totales as $archivo => $t) {
            $ind_archivos[$archivo][] = calcularIndicadores($t, $archivo);
        }
    }

    $anterior = obtenerTotales($p, mismoDiaAnioAnterior($desde), mismoDiaAnioAnterior($hasta));
    if ($anterior !== false && isset($anterior['CMU'])) {
        $ind_aa[] = calcularIndicadores($anterior['CMU'], 'CMU');
    }

    if (isset($totales['CMU']) && cmuCubreRango($p, $desde, $hasta)) {
        $ind_estimado[] = calcularIndicadores($totales['CMU'], 'CMU');
    } else {
        $estimado = obtenerTotalesEstimado($p, $desde, $hasta, $hoy);
        if ($estimado !== false) $ind_estimado[] = calcularIndicadores($estimado);
    }

    $forecast = obtenerTotalesForecast($p, $desde, $hasta);
    if ($forecast !== false && count($forecast) > 0) {
        $ind_forecast[] = calcularIndicadores($forecast);
    }
}

$indicadores = array();
foreach ($ind_archivos as $archivo => $lista) {
    $indicadores[$archivo] = consolidarIndicadores($lista);
}
if (count($ind_aa) > 0) {
    $indicadores['AA'] = consolidarIndicadores($ind_aa);
}
if (count($ind_estimado) > 0) {
    $indicadores['ESTIMADO'] = consolidarIndicadores($ind_estimado);
}
if (count($ind_forecast) > 0) {
    $indicadores['FORECAST_PERIODO'] = consolidarIndicadores($ind_forecast);
}

responder(array(
    'pais'        => $pais,
    'desde'       => $desde,
    'hasta'       => $hasta,
    'indicadores' => (object)$indicadores
));