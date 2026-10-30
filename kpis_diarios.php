<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/lib/respuesta.php';
require_once __DIR__ . '/lib/cac.php';
require_once __DIR__ . '/lib/indicadores.php';

$pais    = param('pais', 'PERU');
$archivo = strtoupper(param('archivo', 'ESTIMADO'));
$desde   = param('desde', date('Y-m-01'));
$hasta   = param('hasta', date('Y-m-d'));

if ($desde > $hasta) responderError('Rango de fechas inválido');

$archivos_necesarios = array('BUDGET');
if ($archivo === 'REAL_MES' || $archivo === 'ESTIMADO') $archivos_necesarios[] = 'REAL_MES';
if ($archivo === 'FORECAST' || $archivo === 'FORECAST_PERIODO') $archivos_necesarios[] = 'FORECAST';

$paises_activos = obtenerPaisesActivos($pais, $desde, $hasta);

$datos_por_pais = array();
foreach ($paises_activos as $p) {
    $d = obtenerDiariosRango($p, $desde, $hasta, $archivos_necesarios);
    if ($d !== false) $datos_por_pais[$p] = $d;
}

$indicadores_base = calcularIndicadores(array());
$serie = array();
foreach (array_keys($indicadores_base) as $k) {
    $serie[$k] = array();
}

$hoy = date('Y-m-d');

for ($t = strtotime($desde), $fin = strtotime($hasta); $t <= $fin; $t = strtotime('+1 day', $t)) {
    $fecha = date('Y-m-d', $t);
    $inds_del_dia = array(); 

    foreach ($paises_activos as $p) {
        $d = isset($datos_por_pais[$p]) ? $datos_por_pais[$p] : array();
        $real     = isset($d['REAL_MES']) ? $d['REAL_MES'] : array();
        $budget   = isset($d['BUDGET']) ? $d['BUDGET'] : array();
        $forecast = isset($d['FORECAST']) ? $d['FORECAST'] : array();
        
        $conceptos = array_unique(array_merge(array_keys($budget), array_keys($real), array_keys($forecast)));
        $t_dia = array();
        
        foreach ($conceptos as $c) {
            $valor = null;
            if ($archivo === 'ESTIMADO') {
                if ($fecha < $hoy && isset($real[$c])) {
                    $valor = isset($real[$c][$fecha]) ? $real[$c][$fecha] : 0.0;
                } else {
                    $valor = isset($budget[$c][$fecha]) ? $budget[$c][$fecha] : null;
                }
            } else if ($archivo === 'FORECAST_PERIODO') {
                $fuente = isset($forecast[$c]) ? $forecast[$c] : (isset($budget[$c]) ? $budget[$c] : array());
                $valor = isset($fuente[$fecha]) ? $fuente[$fecha] : null;
            } else {
                $fuente = isset($d[$archivo][$c]) ? $d[$archivo][$c] : array();
                $valor = isset($fuente[$fecha]) ? $fuente[$fecha] : null;
            }

            if ($valor !== null) {
                $t_dia[$c] = array('suma' => $valor, 'promedio' => $valor);
            }
        }
        
        $inds_del_dia[] = calcularIndicadores($t_dia, $archivo);
    }

    $calculado = consolidarIndicadores($inds_del_dia);
    foreach ($calculado as $k => $v) {
        $serie[$k][] = $v;
    }
}

responder(array(
    'pais'    => $pais,
    'archivo' => $archivo,
    'desde'   => $desde,
    'hasta'   => $hasta,
    'serie'   => $serie
));