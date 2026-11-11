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
$mes_cerrado  = false;
$dias = diasRango($desde, $hasta);

foreach ($paises_activos as $p) {
    
    $totales = obtenerTotales($p, $desde, $hasta);
    if ($totales !== false) {
        if (isset($totales['CMU'])) {
            $mes_cerrado = true;
        }
        foreach ($totales as $archivo => $t) {
            $ind_archivos[$archivo][] = calcularIndicadores($t, $archivo, $dias);
        }
    }

    $anterior = obtenerTotales($p, mismoDiaAnioAnterior($desde), mismoDiaAnioAnterior($hasta));
    if ($anterior !== false && isset($anterior['CMU'])) {
        $ind_aa[] = calcularIndicadores(completarCmuConReal($anterior['CMU'], isset($anterior['REAL_MES']) ? $anterior['REAL_MES'] : null), 'CMU', $dias, true);
    }

    if (isset($totales['CMU']) && cmuCubreRango($p, $desde, $hasta)) {
        // Mes cerrado: el CMU manda; lo que no trae se completa con REAL_MES
        $ind_estimado[] = calcularIndicadores(completarCmuConReal($totales['CMU'], isset($totales['REAL_MES']) ? $totales['REAL_MES'] : null), 'CMU', $dias);
    } else {
        $estimado = obtenerTotalesEstimado($p, $desde, $hasta, $hoy);
        if ($estimado !== false) $ind_estimado[] = calcularIndicadores($estimado, 'ESTIMADO', $dias);
    }

    $forecast = obtenerTotalesForecast($p, $desde, $hasta);
    if ($forecast !== false && count($forecast) > 0) {
        $ind_forecast[] = calcularIndicadores($forecast, 'FORECAST_PERIODO', $dias);
    }
}

$indicadores = array();
foreach ($ind_archivos as $archivo => $lista) {
    $indicadores[$archivo] = consolidarIndicadores($lista, $dias);
}
if (count($ind_aa) > 0) {
    $indicadores['AA'] = consolidarIndicadores($ind_aa, $dias);
}
if (count($ind_estimado) > 0) {
    $indicadores['ESTIMADO'] = consolidarIndicadores($ind_estimado, $dias);
}
if (count($ind_forecast) > 0) {
    $indicadores['FORECAST_PERIODO'] = consolidarIndicadores($ind_forecast, $dias);
}

responder(array(
    'pais'        => $pais,
    'desde'       => $desde,
    'hasta'       => $hasta,
    'mes_cerrado' => $mes_cerrado,
    'indicadores' => (object)$indicadores
));