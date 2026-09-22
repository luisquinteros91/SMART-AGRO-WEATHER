<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba de datos</title>
</head>

<body>

<h1>Probar registro</h1>

<form method="post" action="recibir_datos.php">

    <input
        type="hidden"
        name="api_key"
        value="CONFIGURAR_API_KEY"
    >

    <label>
        Temperatura DHT11:
        <input
            type="number"
            step="0.01"
            name="temperatura_dht"
            value="28.5"
            required
        >
    </label>

    <br><br>

    <label>
        Humedad:
        <input
            type="number"
            step="0.01"
            name="humedad_ambiente"
            value="65"
            required
        >
    </label>

    <br><br>

    <label>
        Sensor de agua:
        <input
            type="number"
            name="valor_agua"
            value="50"
            required
        >
    </label>

    <br><br>

    <label>
        Luminosidad:
        <input
            type="number"
            name="luminosidad"
            value="700"
            required
        >
    </label>

    <br><br>

    <label>
        Temperatura LM35:
        <input
            type="number"
            step="0.01"
            name="temperatura_lm35"
            value="28.1"
            required
        >
    </label>

    <br><br>

    <button type="submit">
        Enviar medición
    </button>

</form>

</body>
</html>