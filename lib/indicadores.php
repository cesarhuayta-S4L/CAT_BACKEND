<?php
function suma($t, $c)  { return isset($t[$c]) ? $t[$c]['suma'] : null; }
function suma0($t, $c) { return isset($t[$c]) ? $t[$c]['suma'] : 0; }
function prom($t, $c)  { return isset($t[$c]) ? $t[$c]['promedio'] : null; }
function dividir($a, $b) { return ($a === null || $b === null || $b == 0) ? null : $a / $b; }

function calcularIndicadores($t) {
    $leads        = suma($t, 'LEADS');
    $inversion    = suma($t, 'INVERSION (MILLONES)');
    $inversionUsd = ($inversion === null) ? null : $inversion * 1000000;
    $brutas       = suma($t, 'MATRICULAS');
    $netas        = ($brutas === null) ? null : $brutas - suma0($t, 'CANCELACIONES');
    $agentes      = prom($t, 'AGENTES INICIO TOTAL');

    $marketing = ($inversionUsd === null ? 0 : $inversionUsd)
               + suma0($t, 'REMUNERACIONES MAC')
               + suma0($t, 'BRANDING')
               + suma0($t, 'TELEFONO MAC')
               + suma0($t, 'OTROS GASTOS MAC')
               + suma0($t, 'TELEFONIA MAC');

    $costoTel    = prom($t, 'COSTO DE TELEFONO POR LEAD');
    $telefonoSac = ($costoTel !== null && $leads !== null) ? $costoTel * $leads : 0;

    $ventas = suma0($t, 'COSTO DE PERSONAL')
            + suma0($t, 'REMUNERACIONES SOPORTE')
            + suma0($t, 'TERCEROS')
            + suma0($t, 'OTROS GASTOS SAC')
            + $telefonoSac;

    return array(
        'leads'          => $leads,
        'cpl'            => dividir($inversionUsd, $leads),
        'inversion'      => $inversion,
        'brutas'         => $brutas,
        'netas'          => $netas,
        'cr'             => dividir($brutas, $leads),
        'agentes'        => $agentes,
        'vpa'            => dividir($brutas, $agentes),
        'lpa'            => dividir($leads, $agentes),
        'marketing_cost' => $marketing,
        'sales_cost'     => $ventas,
        'mac'            => dividir($marketing, $brutas),
        'sac'            => dividir($ventas, $brutas),
        'cac_neto'       => dividir($marketing + $ventas, $netas),
        'sac_mas_mac'    => $marketing + $ventas
    );
}