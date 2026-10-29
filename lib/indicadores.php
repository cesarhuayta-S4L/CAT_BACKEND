<?php
function suma($t, $c)  { return isset($t[$c]) ? $t[$c]['suma'] : null; }
function suma0($t, $c) { return isset($t[$c]) ? $t[$c]['suma'] : 0; }
function prom($t, $c)  { return isset($t[$c]) ? $t[$c]['promedio'] : null; }
function dividir($a, $b) { return ($a === null || $b === null || $b == 0) ? null : $a / $b; }

function calcularIndicadores($t, $archivo = '') {
    $leads        = suma($t, 'LEADS');
    $iva          = prom($t, 'IVA');

    $tasaIva   = ($iva !== null && $iva > 0) ? $iva : 0.16;
    $cplConIva = prom($t, 'CPL CON IVA');
    $cplSinIva = ($cplConIva === null) ? null : $cplConIva / (1 + $tasaIva);

    // Solo CMU: INVERSION (MILLONES) viene con IVA y en moneda completa (en los demás archivos va en millones),
    // se usa tal cual; en el resto y si falta, leads * CPL sin IVA.
    $inversionUsd = ($archivo === 'CMU') ? suma($t, 'INVERSION (MILLONES)') : null;
    if ($inversionUsd === null) {
        $inversionUsd = ($leads === null || $cplSinIva === null) ? null : $leads * $cplSinIva;
    }
    $inversion    = ($inversionUsd === null) ? null : $inversionUsd / 1000000;
    
    $brutas       = suma($t, 'MATRICULAS');
    $netas        = ($brutas === null) ? null : $brutas - suma0($t, 'CANCELACIONES');
    
    $agInicio     = prom($t, 'AGENTES INICIO TOTAL');
    $agFinal      = prom($t, 'AGENTES FINAL TOTAL');
    $agentes      = ($agInicio !== null && $agFinal !== null) ? ($agInicio + $agFinal) / 2
                  : ($agInicio !== null ? $agInicio : $agFinal);

    $marketingDirecto = suma($t, 'MARKETING COST');
    if ($marketingDirecto !== null) {
        $marketing = $marketingDirecto;
    } else {
        $marketing = ($inversionUsd === null ? 0 : $inversionUsd)
                   + suma0($t, 'REMUNERACIONES MAC')
                   + suma0($t, 'BRANDING')
                   + suma0($t, 'TELEFONO MAC')
                   + suma0($t, 'OTROS GASTOS MAC')
                   + suma0($t, 'TELEFONIA MAC');
    }

    $ventasDirecto = suma($t, 'SALES COST');
    if ($ventasDirecto !== null) {
        $ventas = $ventasDirecto;
    } else {
        $costoTel    = prom($t, 'COSTO DE TELEFONO POR LEAD');
        $telefonoSac = ($costoTel !== null && $leads !== null) ? $costoTel * $leads : 0;

        $ventas = suma0($t, 'COSTO DE PERSONAL')
                + suma0($t, 'REMUNERACIONES SOPORTE')
                + suma0($t, 'TERCEROS')
                + suma0($t, 'OTROS GASTOS SAC')
                + suma0($t, 'BOLSA MILLONARIA')
                + $telefonoSac;
    }

    return array(
        'leads'          => $leads,
        'cpl'            => $cplSinIva,
        'inversion'      => $inversion,
        'iva'            => $iva,
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
        'cac_bruto'      => dividir($marketing + $ventas, $brutas),
        'cac_neto'       => dividir($marketing + $ventas, $netas),
        'sac_mas_mac'    => $marketing + $ventas
    );
}