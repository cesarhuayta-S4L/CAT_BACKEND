<?php
require_once __DIR__ . "/database.php";

$conexion = @new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, (int)DB_PORT);

if ($conexion->connect_errno) {
    error_log("Falló conexión a la base de datos: " . $conexion->connect_error);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array("error" => "Error de conexión a la base de datos"));
    exit;
}

$conexion->set_charset(DB_ENCODE);

if (!function_exists('consultar')) {

    function consultar($sql, $tipos = "", $params = array()) {
        global $conexion;
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            error_log("Error SQL: " . $conexion->error . " | Query: " . $sql);
            return false;
        }
        if ($tipos !== "" && count($params) > 0) {
            $refs = array($tipos);
            foreach ($params as $i => $p) { $refs[] = &$params[$i]; }
            call_user_func_array(array($stmt, 'bind_param'), $refs);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $filas = array();
        while ($row = $res->fetch_assoc()) { $filas[] = $row; }
        $stmt->close();
        return $filas;
    }

    // Igual, pero devuelve solo la primera fila (o null)
    function consultarUna($sql, $tipos = "", $params = array()) {
        $filas = consultar($sql, $tipos, $params);
        return ($filas && count($filas) > 0) ? $filas[0] : null;
    }
}