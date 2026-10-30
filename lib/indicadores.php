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
        $telefonoSac = ($archivo === 'CMU') ? suma($t, 'TELEFONO') : null;
        if ($telefonoSac === null) {
            $costoTel    = prom($t, 'COSTO DE TELEFONO POR LEAD');
            $telefonoSac = ($costoTel !== null && $leads !== null) ? $costoTel * $leads : 0;
        }

        $ventas = suma0($t, 'COSTO DE PERSONAL')
                + ($archivo === 'CMU' ? 0 : suma0($t, 'REMUNERACIONES SOPORTE'))
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

function consolidarIndicadores($lista) {
    if (count($lista) === 0) return calcularIndicadores(array());
    if (count($lista) === 1) return $lista[0]; // Si es 1 país, devuelve tal cual

    $suma = array(
        'leads' => 0, 'inversion' => 0, 'brutas' => 0, 'netas' => 0,
        'agentes' => 0, 'marketing_cost' => 0, 'sales_cost' => 0
    );

    foreach ($lista as $ind) {
        $suma['leads']          += (float)$ind['leads'];
        $suma['inversion']      += (float)$ind['inversion'];
        $suma['brutas']         += (float)$ind['brutas'];
        $suma['netas']          += (float)$ind['netas'];
        $suma['agentes']        += (float)$ind['agentes'];
        $suma['marketing_cost'] += (float)$ind['marketing_cost'];
        $suma['sales_cost']     += (float)$ind['sales_cost'];
    }

    $inversionUsd = $suma['inversion'] * 1000000;
    
    return array(
        'leads'          => $suma['leads'],
        'cpl'            => dividir($inversionUsd, $suma['leads']),
        'inversion'      => $suma['inversion'],
        'iva'            => null,
        'brutas'         => $suma['brutas'],
        'netas'          => $suma['netas'],
        'cr'             => dividir($suma['brutas'], $suma['leads']),
        'agentes'        => $suma['agentes'],
        'vpa'            => dividir($suma['brutas'], $suma['agentes']),
        'lpa'            => dividir($suma['leads'], $suma['agentes']),
        'marketing_cost' => $suma['marketing_cost'],
        'sales_cost'     => $suma['sales_cost'],
        'mac'            => dividir($suma['marketing_cost'], $suma['brutas']),
        'sac'            => dividir($suma['sales_cost'], $suma['brutas']),
        'cac_bruto'      => dividir($suma['marketing_cost'] + $suma['sales_cost'], $suma['brutas']),
        'cac_neto'       => dividir($suma['marketing_cost'] + $suma['sales_cost'], $suma['netas']),
        'sac_mas_mac'    => $suma['marketing_cost'] + $suma['sales_cost']
    );
}