<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/lib/respuesta.php';
require_once __DIR__ . '/lib/cac.php';
require_once __DIR__ . '/lib/indicadores.php';

$pais      = param('pais', 'PERU');
$archivo   = strtoupper(param('archivo', 'BUDGET'));
$indicador = param('indicador', 'leads');
$anios     = array_values(array_unique(array_map('intval', array_filter(explode(',', param('anios', '2025,2026')), 'strlen'))));

if (count($anios) === 0) responderError('Debe indicar al menos un año');
foreach ($anios as $a) {
    if ($a < 2000 || $a > 2100) responderError('Año inválido');
}
if (max($anios) - min($anios) > 10) responderError('El rango de años no puede superar 10 años');

// Valida el indicador contra los que calcula calcularIndicadores() antes de consultar la base
if (!array_key_exists($indicador, calcularIndicadores(array()))) responderError('Indicador inválido');

$totales = obtenerTotalesMensuales($pais, min($anios), max($anios));
if ($totales === false) responderError('Error al consultar', 500);

// Valor del indicador por mes (1 a 12); null si no hay datos de ese mes
$serie = array();
foreach ($anios as $a) {
    $meses = array();
    for ($m = 1; $m <= 12; $m++) {
        $meses[] = isset($totales[$a][$m][$archivo]) ? calcularIndicadores($totales[$a][$m][$archivo]) : null;
    }
    $serie[(string)$a] = $meses;
}

foreach ($serie as $a => $meses) {
    foreach ($meses as $i => $ind) {
        $serie[$a][$i] = ($ind === null) ? null : $ind[$indicador];
    }
}

responder(array(
    'pais'      => $pais,
    'archivo'   => $archivo,
    'indicador' => $indicador,
    'anios'     => $anios,
    'serie'     => (object)$serie
));
