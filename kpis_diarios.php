<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/lib/respuesta.php';
require_once __DIR__ . '/lib/cac.php';
require_once __DIR__ . '/lib/indicadores.php';

$pais      = param('pais', 'PERU');
$archivo   = strtoupper(param('archivo', 'REAL_MES'));
$indicador = param('indicador', 'leads');
$desde     = param('desde', date('Y-m-01'));
$hasta     = param('hasta', date('Y-m-d'));

if ($desde > $hasta) responderError('Rango de fechas inválido');
if (!array_key_exists($indicador, calcularIndicadores(array()))) responderError('Indicador inválido');

// Determinamos qué archivos de la BD necesitamos extraer
$archivos_necesarios = array('BUDGET');
if ($archivo === 'REAL_MES' || $archivo === 'ESTIMADO') $archivos_necesarios[] = 'REAL_MES';
if ($archivo === 'FORECAST' || $archivo === 'FORECAST_PERIODO') $archivos_necesarios[] = 'FORECAST';

// Usamos tu función existente para extraer todos los datos diarios de una sola vez
$d = obtenerDiariosRango($pais, $desde, $hasta, $archivos_necesarios);
if ($d === false) responderError('Error al consultar BD', 500);

$real     = isset($d['REAL_MES']) ? $d['REAL_MES'] : array();
$budget   = isset($d['BUDGET']) ? $d['BUDGET'] : array();
$forecast = isset($d['FORECAST']) ? $d['FORECAST'] : array();

// Extraemos todos los conceptos únicos para armar el día
$conceptos = array_unique(array_merge(array_keys($budget), array_keys($real), array_keys($forecast)));

$serie = array();
$hoy = date('Y-m-d');

// Recorremos día por día (misma lógica que tu totalizarRango)
for ($t = strtotime($desde), $fin = strtotime($hasta); $t <= $fin; $t = strtotime('+1 day', $t)) {
    $fecha = date('Y-m-d', $t);
    $t_dia = array();
    
    foreach ($conceptos as $c) {
        $valor = null;
        
        // Aplicamos tu misma regla de negocio, pero día por día
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
            // Para variables directas de BD (REAL_MES, BUDGET)
            $fuente = isset($d[$archivo][$c]) ? $d[$archivo][$c] : array();
            $valor = isset($fuente[$fecha]) ? $fuente[$fecha] : null;
        }

        if ($valor !== null) {
            // calcularIndicadores espera un arreglo con 'suma' y 'promedio'
            $t_dia[$c] = array('suma' => $valor, 'promedio' => $valor);
        }
    }

    // Calcula el CPL, Inversión, Leads del día exacto
    $calculado = calcularIndicadores($t_dia);
    $serie[] = $calculado[$indicador];
}

responder(array(
    'pais'      => $pais,
    'archivo'   => $archivo,
    'indicador' => $indicador,
    'desde'     => $desde,
    'hasta'     => $hasta,
    'serie'     => $serie
));