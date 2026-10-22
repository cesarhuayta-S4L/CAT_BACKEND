<?php
function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos);
    exit;
}

function responderError($mensaje, $codigo = 400) {
    responder(array("error" => $mensaje), $codigo);
}

function param($nombre, $defecto = null) {
    return (isset($_GET[$nombre]) && $_GET[$nombre] !== '') ? trim($_GET[$nombre]) : $defecto;
}

function fechaValida($f) {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

function validarRango($desde, $hasta) {
    if (!fechaValida($desde) || !fechaValida($hasta)) responderError('Fechas inválidas');
    if ($desde > $hasta) responderError('"desde" no puede ser mayor que "hasta"');
    if (substr($desde, 0, 7) !== substr($hasta, 0, 7)) {
        responderError('El rango debe estar dentro de un mismo mes');
    }
}