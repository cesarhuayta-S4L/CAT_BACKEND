<?php

function getFiltroPais($paisParam) {
    if (strtoupper(trim($paisParam)) === 'TODOS' || empty($paisParam)) {
        return array('sql' => '1=1', 'tipos' => '', 'params' => array());
    }
    $paises = array_filter(array_map('trim', explode(',', $paisParam)));
    $marcas = implode(',', array_fill(0, count($paises), '?'));
    return array(
        'sql' => "pais IN ($marcas)",
        'tipos' => str_repeat('s', count($paises)),
        'params' => $paises
    );
}

// Suma y promedio por archivo y concepto, para un país y rango de fechas
function obtenerTotales($pais, $desde, $hasta) {
    $p = getFiltroPais($pais);
    $sql = "SELECT archivo, concepto,
                   SUM(valor_final) AS suma,
                   AVG(valor_final) AS promedio
            FROM datos_cac_dia
            WHERE {$p['sql']} AND fecha BETWEEN ? AND ?
            GROUP BY archivo, concepto";

    $filas = consultar($sql, $p['tipos'] . "ss", array_merge($p['params'], array($desde, $hasta)));
    if ($filas === false) return false;

    $totales = array();
    foreach ($filas as $f) {
        $archivo  = strtoupper(trim($f['archivo']));
        $concepto = strtoupper(trim($f['concepto']));
        $totales[$archivo][$concepto] = array(
            'suma'     => (float)$f['suma'],
            'promedio' => (float)$f['promedio']
        );
    }
    return $totales;
}

// Misma fecha un año antes; el 29 de febrero pasa al 28.
function mismoDiaAnioAnterior($fecha) {
    $anio = (int)substr($fecha, 0, 4) - 1;
    $mesDia = substr($fecha, 5);
    if ($mesDia === '02-29') $mesDia = '02-28';
    return $anio . '-' . $mesDia;
}

// Meses cerrados de CMU: los que tienen dato en todos los días del mes calendario (aunque el rango pedido sea parcial).
// Devuelve ['2026-09' => true, ...] o false si falla la consulta.
function mesesCmuCerrados($pais, $desde, $hasta) {
    $p = getFiltroPais($pais);
    $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, COUNT(DISTINCT fecha) AS dias
            FROM datos_cac_dia
            WHERE {$p['sql']} AND fecha BETWEEN ? AND ?
              AND archivo = 'CMU'
            GROUP BY mes";

    $ini = date('Y-m-01', strtotime($desde));
    $fin = date('Y-m-t', strtotime($hasta));
    $filas = consultar($sql, $p['tipos'] . "ss", array_merge($p['params'], array($ini, $fin)));
    if ($filas === false) return false;

    $cerrados = array();
    foreach ($filas as $f) {
        if ((int)$f['dias'] >= (int)date('t', strtotime($f['mes'] . '-01'))) $cerrados[$f['mes']] = true;
    }
    return $cerrados;
}

// CMU cubre el rango pedido si todos los meses que toca están cerrados.
function cmuCubreRango($pais, $desde, $hasta) {
    $cerrados = mesesCmuCerrados($pais, $desde, $hasta);
    if ($cerrados === false) return false;
    for ($t = strtotime(date('Y-m-01', strtotime($desde))); $t <= strtotime($hasta); $t = strtotime('+1 month', $t)) {
        if (!isset($cerrados[date('Y-m', $t)])) return false;
    }
    return true;
}

// Valores diarios no nulos de un rango, por archivo, concepto y fecha: [archivo][concepto][fecha] => valor
function obtenerDiariosRango($pais, $desde, $hasta, $archivos) {
    $p = getFiltroPais($pais);
    $marcasArchivos = implode(',', array_fill(0, count($archivos), '?'));
    
    $sql = "SELECT archivo, concepto, fecha, valor_final
            FROM datos_cac_dia
            WHERE {$p['sql']} AND fecha BETWEEN ? AND ?
              AND archivo IN ($marcasArchivos)";

    $tipos = $p['tipos'] . "ss" . str_repeat('s', count($archivos));
    $parametros = array_merge($p['params'], array($desde, $hasta), $archivos);
    
    $filas = consultar($sql, $tipos, $parametros);
    if ($filas === false) return false;

    $diarios = array();
    foreach ($filas as $f) {
        if ($f['valor_final'] === null) continue;
        $diarios[strtoupper(trim($f['archivo']))][strtoupper(trim($f['concepto']))][$f['fecha']] = (float)$f['valor_final'];
    }
    return $diarios;
}

// Totales del rango con la misma forma que obtenerTotales() (concepto => suma/promedio),
// armados día a día. $valorDia($concepto, $fecha) devuelve el valor de ese día o null si no hay dato.
function totalizarRango($desde, $hasta, $conceptos, $valorDia) {
    $totales = array();
    foreach ($conceptos as $concepto) {
        $suma = 0.0;
        $n = 0;
        for ($t = strtotime($desde), $fin = strtotime($hasta); $t <= $fin; $t = strtotime('+1 day', $t)) {
            $v = $valorDia($concepto, date('Y-m-d', $t));
            if ($v === null) continue;
            $suma += $v;
            $n++;
        }
        if ($n > 0) $totales[$concepto] = array('suma' => $suma, 'promedio' => $suma / $n);
    }
    return $totales;
}

// ESTIMADO del rango, día a día: CMU solo en meses cerrados completos; si no, REAL_MES para los días
// anteriores a $hoy y, si no hay ninguno, BUDGET. REMUNERACIONES SOPORTE de CMU no entra: ya está en COSTO DE PERSONAL.
function obtenerTotalesEstimado($pais, $desde, $hasta, $hoy) {
    $d = obtenerDiariosRango($pais, $desde, $hasta, array('BUDGET', 'REAL_MES', 'CMU'));
    if ($d === false) return false;
    $real   = isset($d['REAL_MES']) ? $d['REAL_MES'] : array();
    $budget = isset($d['BUDGET']) ? $d['BUDGET'] : array();
    $cmu    = isset($d['CMU']) ? $d['CMU'] : array();
    unset($cmu['REMUNERACIONES SOPORTE']);
    $cerrados = mesesCmuCerrados($pais, $desde, $hasta);
    if ($cerrados === false) $cerrados = array();

    return totalizarRango($desde, $hasta, array_unique(array_merge(array_keys($budget), array_keys($real), array_keys($cmu))),
        function ($c, $fecha) use ($real, $budget, $cmu, $cerrados, $hoy) {
            if (isset($cerrados[substr($fecha, 0, 7)]) && isset($cmu[$c][$fecha])) return $cmu[$c][$fecha];
            if ($fecha < $hoy && isset($real[$c])) return isset($real[$c][$fecha]) ? $real[$c][$fecha] : 0.0;
            return isset($budget[$c][$fecha]) ? $budget[$c][$fecha] : null;
        });
}

function obtenerTotalesForecast($pais, $desde, $hasta) {
    $d = obtenerDiariosRango($pais, $desde, $hasta, array('BUDGET', 'FORECAST'));
    if ($d === false) return false;
    $forecast = isset($d['FORECAST']) ? $d['FORECAST'] : array();
    $budget   = isset($d['BUDGET']) ? $d['BUDGET'] : array();
    if (count($forecast) === 0) return array();

    return totalizarRango($desde, $hasta, array_unique(array_merge(array_keys($budget), array_keys($forecast))),
        function ($c, $fecha) use ($forecast, $budget) {
            $fuente = isset($forecast[$c]) ? $forecast[$c] : (isset($budget[$c]) ? $budget[$c] : array());
            return isset($fuente[$fecha]) ? $fuente[$fecha] : null;
        });
}

function obtenerTotalesMensuales($pais, $anioDesde, $anioHasta) {
    $p = getFiltroPais($pais);
    $sql = "SELECT YEAR(fecha) AS anio, MONTH(fecha) AS mes, archivo, concepto,
                   SUM(valor_final) AS suma,
                   AVG(valor_final) AS promedio
            FROM datos_cac_dia
            WHERE {$p['sql']} AND fecha BETWEEN ? AND ?
            GROUP BY anio, mes, archivo, concepto";

    $filas = consultar($sql, $p['tipos'] . "ss", array_merge($p['params'], array("$anioDesde-01-01", "$anioHasta-12-31")));
    if ($filas === false) return false;

    $totales = array();
    foreach ($filas as $f) {
        $archivo  = strtoupper(trim($f['archivo']));
        $concepto = strtoupper(trim($f['concepto']));
        $totales[(int)$f['anio']][(int)$f['mes']][$archivo][$concepto] = array(
            'suma'     => (float)$f['suma'],
            'promedio' => (float)$f['promedio']
        );
    }
    return $totales;
}

// Leads por día, separados por archiv
function obtenerLeadsDiarios($pais, $desde, $hasta) {
    $p = getFiltroPais($pais);
    $sql = "SELECT archivo, fecha, DAY(fecha) AS dia, SUM(valor_final) AS leads
            FROM datos_cac_dia
            WHERE {$p['sql']} AND fecha BETWEEN ? AND ?
              AND UPPER(TRIM(concepto)) = 'LEADS'
            GROUP BY archivo, fecha
            ORDER BY fecha";

    $filas = consultar($sql, $p['tipos'] . "ss", array_merge($p['params'], array($desde, $hasta)));
    if ($filas === false) return false;

    $serie = array();
    foreach ($filas as $f) {
        $archivo = strtoupper(trim($f['archivo']));
        $serie[$archivo][] = array(
            'fecha' => $f['fecha'],
            'dia'   => (int)$f['dia'],
            'leads' => (float)$f['leads']
        );
    }
    return $serie;
}

function obtenerFiltros() {
    $r = array();
    
    $paisesPermitidos = "'ARGENTINA', 'COLOMBIA', 'DOMINICANA', 'ECUADOR', 'GUATEMALA', 'PERU', 'USA'";
    $r['paises'] = array();
    
    $filasPaises = consultar("SELECT DISTINCT pais AS v FROM datos_cac_dia WHERE valor_final > 0 AND valor_final IS NOT NULL AND pais IN ($paisesPermitidos) ORDER BY v");
    if ($filasPaises) { 
        foreach ($filasPaises as $f) { 
            $r['paises'][] = $f['v']; 
        } 
    }
    
    $columnas = array('conceptos' => 'concepto', 'archivos' => 'archivo');
    foreach ($columnas as $clave => $col) {
        $r[$clave] = array();
        $filas = consultar("SELECT DISTINCT $col AS v FROM datos_cac_dia WHERE valor_final > 0 AND valor_final IS NOT NULL ORDER BY v");
        if ($filas) { 
            foreach ($filas as $f) { 
                $r[$clave][] = $f['v']; 
            } 
        }
    }
    
    $r['rango'] = consultarUna("SELECT MIN(fecha) AS desde, MAX(fecha) AS hasta FROM datos_cac_dia WHERE valor_final > 0 AND valor_final IS NOT NULL");
    
    return $r;
}

function obtenerTotalesDiarios($pais, $desde, $hasta) {
    $p = getFiltroPais($pais);
    $sql = "SELECT fecha, archivo, concepto,
                   SUM(valor_final) AS suma,
                   AVG(valor_final) AS promedio
            FROM datos_cac_dia
            WHERE {$p['sql']} AND fecha BETWEEN ? AND ?
            GROUP BY fecha, archivo, concepto";

    $filas = consultar($sql, $p['tipos'] . "ss", array_merge($p['params'], array($desde, $hasta)));
    if ($filas === false) return false;

    $totales = array();
    foreach ($filas as $f) {
        $fecha    = $f['fecha'];
        $archivo  = strtoupper(trim($f['archivo']));
        $concepto = strtoupper(trim($f['concepto']));
        
        $totales[$fecha][$archivo][$concepto] = array(
            'suma'     => (float)$f['suma'],
            'promedio' => (float)$f['promedio']
        );
    }
    return $totales;
}

function obtenerPaisesActivos($pais, $desde, $hasta) {
    $p = getFiltroPais($pais);
    $sql = "SELECT DISTINCT pais FROM datos_cac_dia WHERE {$p['sql']} AND fecha BETWEEN ? AND ?";
    $filas = consultar($sql, $p['tipos'] . "ss", array_merge($p['params'], array($desde, $hasta)));
    $res = array();
    if ($filas) {
        foreach ($filas as $f) {
            $res[] = strtoupper(trim($f['pais']));
        }
    }
    return $res;
}