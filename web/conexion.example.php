<?php
declare(strict_types=1);

$servidor = "localhost";
$usuario  = "TU_USUARIO_MYSQL";
$clave    = "TU_PASSWORD_MYSQL";
$base     = "TU_BASE_DE_DATOS";

$conexion = new mysqli($servidor, $usuario, $clave, $base);

if ($conexion->connect_error) {
    http_response_code(500);
    exit("Error de conexión con la base de datos.");
}

$conexion->set_charset("utf8mb4");
date_default_timezone_set("America/Argentina/Cordoba");
