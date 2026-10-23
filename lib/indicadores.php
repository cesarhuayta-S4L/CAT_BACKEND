<?php
function suma($t, $c)  { return isset($t[$c]) ? $t[$c]['suma'] : null; }
function suma0($t, $c) { return isset($t[$c]) ? $t[$c]['suma'] : 0; }
function prom($t, $c)  { return isset($t[$c]) ? $t[$c]['promedio'] : null; }
function dividir($a, $b) { return ($a === null || $b === null || $b == 0) ? null : $a / $b; }

function calcularIndicadores($t) {
    // IVA es un dato fijo repetido cada día: se promedia, no se suma.
    // Leads es un dato diario, por eso se suma.
    $leads        = suma($t, 'LEADS');
    $iva          = prom($t, 'IVA');

    // CPL es un dato fijo de la tabla (CPL CON IVA). El concepto IVA viene en 0, así que se usa
    // 16 %, que es la tasa con la que se calculó el CPL de la tabla (inversión / leads del mes * 1.16)
    $tasaIva   = ($iva !== null && $iva > 0) ? $iva : 0.16;
    $cplConIva = prom($t, 'CPL CON IVA');
    $cplSinIva = ($cplConIva === null) ? null : $cplConIva / (1 + $tasaIva);

    // Inversión = leads * CPL sin IVA (en dólares); 'inversion' va en millones
    $inversionUsd = ($leads === null || $cplSinIva === null) ? null : $leads * $cplSinIva;
    $inversion    = ($inversionUsd === null) ? null : $inversionUsd / 1000000;
    $brutas       = suma($t, 'MATRICULAS');
    $netas        = ($brutas === null) ? null : $brutas - suma0($t, 'CANCELACIONES');
    // Agentes: promedio entre los agentes al inicio y al final del mes
    $agInicio     = prom($t, 'AGENTES INICIO TOTAL');
    $agFinal      = prom($t, 'AGENTES FINAL TOTAL');
    $agentes      = ($agInicio !== null && $agFinal !== null) ? ($agInicio + $agFinal) / 2
                  : ($agInicio !== null ? $agInicio : $agFinal);

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
        'cac_neto'       => dividir($marketing + $ventas, $netas),
        'sac_mas_mac'    => $marketing + $ventas
    );
}