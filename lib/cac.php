<?php
// Suma y promedio por archivo y concepto, para un país y rango de fechas
function obtenerTotales($pais, $desde, $hasta) {
    $sql = "SELECT archivo, concepto,
                   SUM(valor_final) AS suma,
                   AVG(valor_final) AS promedio
            FROM datos_cac_dia
            WHERE pais = ? AND fecha BETWEEN ? AND ?
            GROUP BY archivo, concepto";

    $filas = consultar($sql, "sss", array($pais, $desde, $hasta));
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

// Leads por día, separados por archiv
function obtenerLeadsDiarios($pais, $desde, $hasta) {
    $sql = "SELECT archivo, fecha, DAY(fecha) AS dia, SUM(valor_final) AS leads
            FROM datos_cac_dia
            WHERE pais = ? AND fecha BETWEEN ? AND ?
              AND UPPER(TRIM(concepto)) = 'LEADS'
            GROUP BY archivo, fecha
            ORDER BY fecha";

    $filas = consultar($sql, "sss", array($pais, $desde, $hasta));
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
    $columnas = array('paises' => 'pais', 'conceptos' => 'concepto', 'archivos' => 'archivo');
    $r = array();
    foreach ($columnas as $clave => $col) {
        $r[$clave] = array();
        $filas = consultar("SELECT DISTINCT $col AS v FROM datos_cac_dia ORDER BY v");
        if ($filas) { foreach ($filas as $f) { $r[$clave][] = $f['v']; } }
    }
    $r['rango'] = consultarUna("SELECT MIN(fecha) AS desde, MAX(fecha) AS hasta FROM datos_cac_dia");
    return $r;
}