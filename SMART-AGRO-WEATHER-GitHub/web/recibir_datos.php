<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

require_once "conexion.php";

/*
|--------------------------------------------------------------------------
| Clave privada de la estación
|--------------------------------------------------------------------------
| Debe coincidir exactamente con la clave utilizada
| en el programa Python.
*/

$claveCorrecta = getenv("SMART_AGRO_API_KEY") ?: "";

if ($claveCorrecta === "") {
    http_response_code(500);
    echo json_encode([
        "estado" => "error",
        "mensaje" => "API key no configurada en el servidor"
    ]);
    exit;
}

$claveRecibida = $_POST["api_key"] ?? "";

if (
    $claveRecibida === "" ||
    !hash_equals($claveCorrecta, $claveRecibida)
) {
    http_response_code(403);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Estación no autorizada"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Recibir mediciones
|--------------------------------------------------------------------------
*/

$temperaturaDht = filter_input(
    INPUT_POST,
    "temperatura_dht",
    FILTER_VALIDATE_FLOAT
);

$humedad = filter_input(
    INPUT_POST,
    "humedad_ambiente",
    FILTER_VALIDATE_FLOAT
);

$agua = filter_input(
    INPUT_POST,
    "valor_agua",
    FILTER_VALIDATE_INT
);

$luminosidad = filter_input(
    INPUT_POST,
    "luminosidad",
    FILTER_VALIDATE_INT
);

$temperaturaLm35 = filter_input(
    INPUT_POST,
    "temperatura_lm35",
    FILTER_VALIDATE_FLOAT
);

/*
|--------------------------------------------------------------------------
| Verificar que todos los datos hayan llegado
|--------------------------------------------------------------------------
*/

if (
    $temperaturaDht === false ||
    $temperaturaDht === null ||
    $humedad === false ||
    $humedad === null ||
    $agua === false ||
    $agua === null ||
    $luminosidad === false ||
    $luminosidad === null ||
    $temperaturaLm35 === false ||
    $temperaturaLm35 === null
) {
    http_response_code(422);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Datos incompletos o inválidos"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validar rangos razonables
|--------------------------------------------------------------------------
*/

if ($temperaturaDht < -20 || $temperaturaDht > 80) {
    http_response_code(422);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Temperatura DHT11 fuera de rango"
    ]);

    exit;
}

if ($humedad < 0 || $humedad > 100) {
    http_response_code(422);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Humedad fuera de rango"
    ]);

    exit;
}

if ($agua < 0 || $agua > 1023) {
    http_response_code(422);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Valor del sensor de agua fuera de rango"
    ]);

    exit;
}

if ($luminosidad < 0 || $luminosidad > 1023) {
    http_response_code(422);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Valor de luminosidad fuera de rango"
    ]);

    exit;
}

if ($temperaturaLm35 < -20 || $temperaturaLm35 > 150) {
    http_response_code(422);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Temperatura LM35 fuera de rango"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Interpretar el sensor de agua
|--------------------------------------------------------------------------
| Estos valores son iniciales y luego pueden calibrarse
| según las lecturas reales de tu sensor.
*/

if ($agua < 100) {
    $estadoLluvia = "SIN LLUVIA";
} elseif ($agua < 400) {
    $estadoLluvia = "GOTAS";
} else {
    $estadoLluvia = "LLUVIA";
}

/*
|--------------------------------------------------------------------------
| Guardar medición en MySQL
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO mediciones (
        temperatura_dht,
        humedad_ambiente,
        valor_agua,
        luminosidad,
        temperatura_lm35,
        estado_lluvia
    )
    VALUES (?, ?, ?, ?, ?, ?)
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "No se pudo preparar la consulta SQL",
        "detalle" => $conexion->error
    ]);

    exit;
}

$stmt->bind_param(
    "ddiids",
    $temperaturaDht,
    $humedad,
    $agua,
    $luminosidad,
    $temperaturaLm35,
    $estadoLluvia
);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "No se pudo guardar la medición",
        "detalle" => $stmt->error
    ]);

    $stmt->close();
    $conexion->close();

    exit;
}

/*
|--------------------------------------------------------------------------
| Respuesta correcta
|--------------------------------------------------------------------------
*/

echo json_encode([
    "estado" => "ok",
    "mensaje" => "Medición registrada correctamente",
    "id" => $stmt->insert_id,
    "datos" => [
        "temperatura_dht" => $temperaturaDht,
        "humedad_ambiente" => $humedad,
        "valor_agua" => $agua,
        "luminosidad" => $luminosidad,
        "temperatura_lm35" => $temperaturaLm35,
        "estado_lluvia" => $estadoLluvia
    ]
]);

$stmt->close();
$conexion->close();