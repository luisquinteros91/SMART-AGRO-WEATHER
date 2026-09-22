<?php

require_once "conexion.php";

/* ==========================================================
   SMART AGRO WEATHER
   Colegio Sagrada Familia - Añatuya
   ========================================================== */


/* ==========================================================
   1. ÚLTIMA MEDICIÓN
   ========================================================== */

/*
   TIMESTAMPDIFF permite calcular directamente desde MySQL
   cuántos segundos pasaron desde la última medición.
*/

$sql = "
    SELECT *,
           TIMESTAMPDIFF(
               SECOND,
               fecha_hora,
               NOW()
           ) AS segundos_sin_datos
    FROM mediciones
    ORDER BY id DESC
    LIMIT 1
";

$resultado = $conexion->query($sql);

$dato = $resultado ? $resultado->fetch_assoc() : null;


/* ==========================================================
   2. DATOS POR DEFECTO
   ========================================================== */

if (!$dato) {

    $dato = [
        "temperatura_dht" => 0,
        "humedad_ambiente" => 0,
        "valor_agua" => 0,
        "luminosidad" => 0,
        "temperatura_lm35" => 0,
        "estado_lluvia" => "SIN DATOS",
        "fecha_hora" => "-",
        "segundos_sin_datos" => null
    ];
}


/* ==========================================================
   3. ESTADO ONLINE / OFFLINE
   ========================================================== */

/*
   Python actualmente envía aproximadamente cada 30 segundos.

   Consideramos ONLINE si recibimos datos dentro
   de los últimos 120 segundos (2 minutos).
*/

$limiteOnline = 120;

$segundosSinDatos =
    $dato["segundos_sin_datos"] !== null
    ? (int)$dato["segundos_sin_datos"]
    : null;

$estacionOnline = false;

if (
    $segundosSinDatos !== null &&
    $segundosSinDatos >= 0 &&
    $segundosSinDatos <= $limiteOnline
) {
    $estacionOnline = true;
}


/* ==========================================================
   4. TEXTO DEL TIEMPO SIN CONEXIÓN
   ========================================================== */

$textoSinDatos = "";

if ($segundosSinDatos !== null) {

    if ($segundosSinDatos < 60) {

        $textoSinDatos =
            $segundosSinDatos .
            " segundos";

    } elseif ($segundosSinDatos < 3600) {

        $minutos =
            floor(
                $segundosSinDatos / 60
            );

        $textoSinDatos =
            $minutos .
            " minuto(s)";

    } elseif ($segundosSinDatos < 86400) {

        $horas =
            floor(
                $segundosSinDatos / 3600
            );

        $textoSinDatos =
            $horas .
            " hora(s)";

    } else {

        $dias =
            floor(
                $segundosSinDatos / 86400
            );

        $textoSinDatos =
            $dias .
            " día(s)";
    }
}


/* ==========================================================
   5. HISTORIAL PARA TENDENCIAS
   ========================================================== */

$sqlHistorial = "
    SELECT *
    FROM mediciones
    ORDER BY id DESC
    LIMIT 12
";

$resultadoHistorial =
    $conexion->query(
        $sqlHistorial
    );

$mediciones = [];

if ($resultadoHistorial) {

    while (
        $fila =
        $resultadoHistorial->fetch_assoc()
    ) {

        $mediciones[] =
            $fila;
    }
}


/* ==========================================================
   6. VALORES INICIALES DEL ASISTENTE
   ========================================================== */

$mensajeClima =
    "Todavía no hay suficientes datos para analizar tendencias.";

$probabilidadLluvia =
    "SIN DATOS";

$estadoCalor =
    "SIN DATOS";

$tendenciaTemp =
    "SIN DATOS";

$tendenciaHumedad =
    "SIN DATOS";

$tendenciaLuz =
    "SIN DATOS";

$estadoGeneral =
    "NORMAL";

$mensajeEstadoGeneral =
    "Sistema funcionando normalmente.";

$claseEstadoGeneral =
    "estado-normal";


/* ==========================================================
   7. ANÁLISIS DE TENDENCIAS
   ========================================================== */

/*
   Solo analizamos el clima si la estación está ONLINE.
*/

if (
    $estacionOnline &&
    count($mediciones) >= 3
) {

    $actual =
        $mediciones[0];

    $anterior =
        $mediciones[
            count($mediciones) - 1
        ];


    /* ------------------------------------------------------
       VARIABLES
       ------------------------------------------------------ */

    $tempActual =
        (float)$actual[
            "temperatura_dht"
        ];

    $tempAnterior =
        (float)$anterior[
            "temperatura_dht"
        ];

    $humActual =
        (float)$actual[
            "humedad_ambiente"
        ];

    $humAnterior =
        (float)$anterior[
            "humedad_ambiente"
        ];

    $luzActual =
        (int)$actual[
            "luminosidad"
        ];

    $luzAnterior =
        (int)$anterior[
            "luminosidad"
        ];

    $aguaActual =
        (int)$actual[
            "valor_agua"
        ];


    /* ======================================================
       TENDENCIA DE TEMPERATURA
       ====================================================== */

    $diferenciaTemp =
        $tempActual -
        $tempAnterior;

    if (
        $diferenciaTemp > 2
    ) {

        $tendenciaTemp =
            "SUBIENDO RÁPIDAMENTE";

    } elseif (
        $diferenciaTemp > 0.5
    ) {

        $tendenciaTemp =
            "SUBIENDO";

    } elseif (
        $diferenciaTemp < -2
    ) {

        $tendenciaTemp =
            "BAJANDO RÁPIDAMENTE";

    } elseif (
        $diferenciaTemp < -0.5
    ) {

        $tendenciaTemp =
            "BAJANDO";

    } else {

        $tendenciaTemp =
            "ESTABLE";
    }


    /* ======================================================
       TENDENCIA DE HUMEDAD
       ====================================================== */

    $diferenciaHumedad =
        $humActual -
        $humAnterior;

    if (
        $diferenciaHumedad > 5
    ) {

        $tendenciaHumedad =
            "AUMENTANDO";

    } elseif (
        $diferenciaHumedad < -5
    ) {

        $tendenciaHumedad =
            "DISMINUYENDO";

    } else {

        $tendenciaHumedad =
            "ESTABLE";
    }


    /* ======================================================
       TENDENCIA DE LUMINOSIDAD
       ====================================================== */

    $diferenciaLuz =
        $luzActual -
        $luzAnterior;

    if (
        $diferenciaLuz > 100
    ) {

        $tendenciaLuz =
            "AUMENTANDO";

    } elseif (
        $diferenciaLuz < -100
    ) {

        $tendenciaLuz =
            "DISMINUYENDO";

    } else {

        $tendenciaLuz =
            "ESTABLE";
    }


    /* ======================================================
       NIVEL DE CALOR
       ====================================================== */

    if (
        $tempActual >= 38
    ) {

        $estadoCalor =
            "MUY ALTO";

    } elseif (
        $tempActual >= 33
    ) {

        $estadoCalor =
            "ALTO";

    } elseif (
        $tempActual >= 28
    ) {

        $estadoCalor =
            "MODERADO";

    } else {

        $estadoCalor =
            "NORMAL";
    }


    /* ======================================================
       PROBABILIDAD LOCAL DE LLUVIA
       ====================================================== */

    $puntosLluvia = 0;


    /* Humedad actual */

    if (
        $humActual >= 85
    ) {

        $puntosLluvia += 3;

    } elseif (
        $humActual >= 75
    ) {

        $puntosLluvia += 2;

    } elseif (
        $humActual >= 65
    ) {

        $puntosLluvia += 1;
    }


    /* Tendencia humedad */

    if (
        $diferenciaHumedad > 8
    ) {

        $puntosLluvia += 2;

    } elseif (
        $diferenciaHumedad > 4
    ) {

        $puntosLluvia += 1;
    }


    /* Disminución de luz */

    if (
        $diferenciaLuz < -200
    ) {

        $puntosLluvia += 2;

    } elseif (
        $diferenciaLuz < -100
    ) {

        $puntosLluvia += 1;
    }


    /* Sensor Water */

    if (
        $aguaActual >= 400
    ) {

        $puntosLluvia += 4;

    } elseif (
        $aguaActual >= 100
    ) {

        $puntosLluvia += 2;
    }


    /* Interpretación */

    if (
        $puntosLluvia >= 7
    ) {

        $probabilidadLluvia =
            "MUY ALTA";

    } elseif (
        $puntosLluvia >= 5
    ) {

        $probabilidadLluvia =
            "ALTA";

    } elseif (
        $puntosLluvia >= 3
    ) {

        $probabilidadLluvia =
            "MODERADA";

    } else {

        $probabilidadLluvia =
            "BAJA";
    }


    /* ======================================================
       MENSAJE DEL ASISTENTE
       ====================================================== */

    $mensajeClima = "";


    /* Temperatura */

    if (
        $tendenciaTemp ===
        "SUBIENDO RÁPIDAMENTE"
    ) {

        $mensajeClima .=
            "La temperatura está aumentando rápidamente. ";

    } elseif (
        $tendenciaTemp ===
        "SUBIENDO"
    ) {

        $mensajeClima .=
            "La temperatura presenta una tendencia ascendente. ";

    } elseif (
        $tendenciaTemp ===
        "BAJANDO RÁPIDAMENTE"
    ) {

        $mensajeClima .=
            "La temperatura está descendiendo rápidamente. ";

    } elseif (
        $tendenciaTemp ===
        "BAJANDO"
    ) {

        $mensajeClima .=
            "La temperatura presenta una tendencia descendente. ";

    } else {

        $mensajeClima .=
            "La temperatura se mantiene estable. ";
    }


    /* Calor */

    if (
        $estadoCalor ===
        "MUY ALTO"
    ) {

        $mensajeClima .=
            "Se registran condiciones de calor intenso. ";

    } elseif (
        $estadoCalor ===
        "ALTO"
    ) {

        $mensajeClima .=
            "Se registran condiciones de calor elevado. ";

    } elseif (
        $estadoCalor ===
        "MODERADO"
    ) {

        $mensajeClima .=
            "La temperatura es moderadamente alta. ";
    }


    /* Lluvia */

    if (
        $probabilidadLluvia ===
        "MUY ALTA"
    ) {

        $mensajeClima .=
            "Las condiciones actuales muestran una probabilidad local muy alta de precipitaciones.";

    } elseif (
        $probabilidadLluvia ===
        "ALTA"
    ) {

        $mensajeClima .=
            "Las condiciones actuales son favorables para posibles precipitaciones.";

    } elseif (
        $probabilidadLluvia ===
        "MODERADA"
    ) {

        $mensajeClima .=
            "Existen algunas condiciones compatibles con posibles precipitaciones.";

    } else {

        $mensajeClima .=
            "Por el momento no se observan señales importantes de lluvia.";
    }


    /* ======================================================
       ESTADO GENERAL
       ====================================================== */

    if (
        $estadoCalor ===
        "MUY ALTO" ||
        $probabilidadLluvia ===
        "MUY ALTA"
    ) {

        $estadoGeneral =
            "ALERTA";

        $mensajeEstadoGeneral =
            "Se detectaron condiciones ambientales que requieren atención.";

        $claseEstadoGeneral =
            "estado-alerta";

    } elseif (
        $estadoCalor ===
        "ALTO" ||
        $probabilidadLluvia ===
        "ALTA"
    ) {

        $estadoGeneral =
            "PRECAUCIÓN";

        $mensajeEstadoGeneral =
            "Se observan condiciones que conviene seguir monitoreando.";

        $claseEstadoGeneral =
            "estado-precaucion";

    } else {

        $estadoGeneral =
            "NORMAL";

        $mensajeEstadoGeneral =
            "Las variables ambientales se encuentran dentro de condiciones normales.";

        $claseEstadoGeneral =
            "estado-normal";
    }
}


/* ==========================================================
   8. SI LA ESTACIÓN ESTÁ OFFLINE
   ========================================================== */

if (!$estacionOnline) {

    $estadoGeneral =
        "SIN CONEXIÓN";

    $mensajeEstadoGeneral =
        "SMART AGRO WEATHER no está enviando nuevas mediciones.";

    $claseEstadoGeneral =
        "estado-desconectado";

    $mensajeClima =
        "La estación se encuentra desconectada. "
        . "El asistente climático ha suspendido temporalmente "
        . "sus análisis hasta recibir nuevas mediciones.";

    $probabilidadLluvia =
        "SIN DATOS";

    $estadoCalor =
        "SIN DATOS";

    $tendenciaTemp =
        "SIN DATOS";

    $tendenciaHumedad =
        "SIN DATOS";

    $tendenciaLuz =
        "SIN DATOS";
}


/* ==========================================================
   9. FUNCIONES DE PRESENTACIÓN
   ========================================================== */

function claseCalor($estado)
{
    if (
        $estado ===
        "MUY ALTO"
    ) {
        return "texto-rojo";
    }

    if (
        $estado ===
        "ALTO"
    ) {
        return "texto-naranja";
    }

    if (
        $estado ===
        "MODERADO"
    ) {
        return "texto-amarillo";
    }

    return "texto-verde";
}


function claseLluvia($estado)
{
    if (
        $estado ===
        "MUY ALTA"
    ) {
        return "texto-azul-fuerte";
    }

    if (
        $estado ===
        "ALTA"
    ) {
        return "texto-azul";
    }

    if (
        $estado ===
        "MODERADA"
    ) {
        return "texto-celeste";
    }

    return "texto-verde";
}


function interpretarLuz($valor)
{
    if (
        $valor < 250
    ) {
        return "BAJA";
    }

    if (
        $valor < 700
    ) {
        return "MEDIA";
    }

    return "ALTA";
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    SMART AGRO WEATHER
</title>

<!-- Actualización automática -->

<meta
    http-equiv="refresh"
    content="15"
>


<style>

/* ==========================================================
   GENERAL
   ========================================================== */

* {
    box-sizing:
        border-box;
}

:root {

    --fondo:
        #eef5f0;

    --fondo-tarjeta:
        #ffffff;

    --texto:
        #1f2937;

    --texto-secundario:
        #64748b;

    --verde:
        #15803d;

    --verde-oscuro:
        #14532d;

    --borde:
        #e2e8f0;
}


body.modo-oscuro {

    --fondo:
        #111827;

    --fondo-tarjeta:
        #1f2937;

    --texto:
        #f8fafc;

    --texto-secundario:
        #cbd5e1;

    --borde:
        #374151;
}


body {

    margin:
        0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        var(--fondo);

    color:
        var(--texto);

    transition:
        background 0.3s,
        color 0.3s;
}


/* ==========================================================
   HEADER
   ========================================================== */

header {

    background:
        linear-gradient(
            135deg,
            #064e3b,
            #15803d
        );

    color:
        white;

    padding:
        28px 15px 35px;

    text-align:
        center;

    position:
        relative;
}


.colegio {

    font-size:
        1rem;

    font-weight:
        bold;

    color:
        #d1fae5;
}


.localidad {

    margin-top:
        5px;

    margin-bottom:
        15px;

    color:
        #bbf7d0;
}


header h1 {

    margin:
        5px 0 8px;

    font-size:
        2.4rem;
}


.subtitulo {

    margin:
        0;

    font-size:
        1.05rem;
}


.feria {

    margin-top:
        14px;

    font-size:
        0.9rem;

    opacity:
        0.9;
}


/* ==========================================================
   MODO OSCURO
   ========================================================== */

.boton-modo {

    position:
        absolute;

    right:
        20px;

    top:
        20px;

    background:
        rgba(
            255,
            255,
            255,
            0.18
        );

    color:
        white;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.3
        );

    padding:
        9px 14px;

    border-radius:
        10px;

    cursor:
        pointer;
}


/* ==========================================================
   CONTENEDOR
   ========================================================== */

.contenedor {

    width:
        min(
            1180px,
            94%
        );

    margin:
        auto;

    padding:
        30px 0 50px;
}


/* ==========================================================
   ESTADO DE CONEXIÓN
   ========================================================== */

.estado-conexion {

    padding:
        22px;

    border-radius:
        18px;

    margin-bottom:
        22px;

    color:
        white;

    text-align:
        center;

    box-shadow:
        0 6px 18px
        rgba(
            0,
            0,
            0,
            0.10
        );
}


.conexion-online {

    background:
        linear-gradient(
            135deg,
            #15803d,
            #22c55e
        );
}


.conexion-offline {

    background:
        linear-gradient(
            135deg,
            #991b1b,
            #dc2626
        );
}


.estado-conexion-titulo {

    font-size:
        1.5rem;

    font-weight:
        bold;
}


.estado-conexion-texto {

    margin-top:
        8px;
}


/* ==========================================================
   ESTADO GENERAL
   ========================================================== */

.estado-general {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

    padding:
        20px 25px;

    border-radius:
        18px;

    margin-bottom:
        25px;

    color:
        white;
}


.estado-general h2 {

    margin:
        0;
}


.estado-general p {

    margin:
        5px 0 0;
}


.estado-normal {

    background:
        linear-gradient(
            135deg,
            #15803d,
            #22c55e
        );
}


.estado-precaucion {

    background:
        linear-gradient(
            135deg,
            #b45309,
            #f59e0b
        );
}


.estado-alerta {

    background:
        linear-gradient(
            135deg,
            #b91c1c,
            #ef4444
        );
}


.estado-desconectado {

    background:
        linear-gradient(
            135deg,
            #475569,
            #64748b
        );
}


/* ==========================================================
   ACTUALIZACIÓN
   ========================================================== */

.actualizacion {

    text-align:
        center;

    margin-bottom:
        25px;
}


.actualizacion h2 {

    margin-bottom:
        8px;

    color:
        var(--verde-oscuro);
}


/* ==========================================================
   TARJETAS
   ========================================================== */

.tarjetas {

    display:
        grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                210px,
                1fr
            )
        );

    gap:
        20px;
}


.tarjeta {

    background:
        var(--fondo-tarjeta);

    border:
        1px solid
        var(--borde);

    border-radius:
        20px;

    padding:
        25px;

    text-align:
        center;

    box-shadow:
        0 6px 20px
        rgba(
            0,
            0,
            0,
            0.07
        );

    transition:
        transform 0.2s;
}


.tarjeta:hover {

    transform:
        translateY(-4px);
}


.tarjeta-dato-viejo {

    opacity:
        0.72;
}


.icono {

    font-size:
        2.8rem;
}


.tarjeta h3 {

    margin:
        12px 0;

    font-size:
        1rem;
}


.valor {

    margin:
        0;

    font-size:
        2rem;

    font-weight:
        bold;

    color:
        var(--verde);
}


.descripcion {

    margin-top:
        8px;

    color:
        var(--texto-secundario);

    font-size:
        0.9rem;
}


/* ==========================================================
   BARRA
   ========================================================== */

.barra {

    height:
        8px;

    background:
        #e5e7eb;

    border-radius:
        20px;

    margin-top:
        16px;

    overflow:
        hidden;
}


.barra span {

    display:
        block;

    height:
        100%;

    background:
        #22c55e;
}


/* ==========================================================
   ASISTENTE
   ========================================================== */

.asistente {

    margin-top:
        35px;

    background:
        var(--fondo-tarjeta);

    border:
        1px solid
        var(--borde);

    border-radius:
        22px;

    padding:
        30px;

    box-shadow:
        0 6px 20px
        rgba(
            0,
            0,
            0,
            0.08
        );
}


.asistente h2 {

    color:
        var(--verde-oscuro);

    margin-top:
        0;
}


.mensaje {

    font-size:
        1.08rem;

    line-height:
        1.7;
}


.datos-asistente {

    display:
        grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                200px,
                1fr
            )
        );

    gap:
        15px;

    margin-top:
        25px;
}


.caja-asistente {

    padding:
        20px;

    border-radius:
        14px;

    background:
        rgba(
            34,
            197,
            94,
            0.08
        );

    text-align:
        center;
}


.caja-asistente strong {

    display:
        block;

    margin-bottom:
        10px;
}


/* ==========================================================
   COLORES
   ========================================================== */

.texto-verde {
    color:
        #15803d;
}

.texto-amarillo {
    color:
        #ca8a04;
}

.texto-naranja {
    color:
        #ea580c;
}

.texto-rojo {
    color:
        #dc2626;
}

.texto-celeste {
    color:
        #0284c7;
}

.texto-azul {
    color:
        #0369a1;
}

.texto-azul-fuerte {
    color:
        #1e3a8a;
}


/* ==========================================================
   NOTA
   ========================================================== */

.nota {

    margin-top:
        25px;

    padding:
        16px;

    border-radius:
        12px;

    background:
        #fff7ed;

    color:
        #9a3412;

    font-size:
        0.9rem;
}


/* ==========================================================
   FOOTER
   ========================================================== */

footer {

    margin-top:
        40px;

    padding:
        25px;

    text-align:
        center;

    color:
        var(--texto-secundario);

    border-top:
        1px solid
        var(--borde);
}


/* ==========================================================
   CELULAR
   ========================================================== */

@media (
    max-width: 650px
) {

    header h1 {

        font-size:
            1.8rem;
    }

    .boton-modo {

        position:
            static;

        margin-bottom:
            15px;
    }

    .estado-general {

        flex-direction:
            column;

        text-align:
            center;
    }
}

</style>

</head>


<body>


<header>

    <button
        class="boton-modo"
        onclick="alternarModoOscuro()"
    >
        🌙 Modo oscuro
    </button>


    <div class="colegio">

        Colegio Sagrada Familia

    </div>


    <div class="localidad">

        Añatuya - Santiago del Estero

    </div>


    <h1>

        SMART AGRO WEATHER

    </h1>


    <p class="subtitulo">

        Plataforma Inteligente de Monitoreo Ambiental

    </p>


    <p class="feria">

        Proyecto de Investigación y Desarrollo Tecnológico
        · Feria de Ciencias 2026

    </p>

</header>


<div class="contenedor">


    <!-- ====================================================
         ESTADO ONLINE / OFFLINE
         ==================================================== -->

    <section
        class="
            estado-conexion
            <?=
                $estacionOnline
                ? "conexion-online"
                : "conexion-offline"
            ?>
        "
    >


        <?php if ($estacionOnline): ?>


            <div class="estado-conexion-titulo">

                🟢 ESTACIÓN ONLINE

            </div>


            <div class="estado-conexion-texto">

                SMART AGRO WEATHER está recibiendo
                mediciones correctamente.

                <?php
                if (
                    $segundosSinDatos !== null
                ):
                ?>

                    <br>

                    Último dato recibido hace

                    <strong>

                        <?=
                        htmlspecialchars(
                            $textoSinDatos
                        )
                        ?>

                    </strong>.

                <?php endif; ?>

            </div>


        <?php else: ?>


            <div class="estado-conexion-titulo">

                🔴 ESTACIÓN DESCONECTADA

            </div>


            <div class="estado-conexion-texto">


                <?php
                if (
                    $segundosSinDatos !== null
                ):
                ?>

                    No se reciben nuevas mediciones
                    desde hace

                    <strong>

                        <?=
                        htmlspecialchars(
                            $textoSinDatos
                        )
                        ?>

                    </strong>.

                <?php else: ?>

                    Todavía no se han recibido
                    mediciones de la estación.

                <?php endif; ?>


                <br><br>

                Verifique Arduino,
                el cable USB y el programa Python.


            </div>


        <?php endif; ?>


    </section>


    <!-- ====================================================
         ESTADO GENERAL
         ==================================================== -->

    <section
        class="
            estado-general
            <?= $claseEstadoGeneral ?>
        "
    >


        <div>

            <h2>

                Estado General:

                <?= htmlspecialchars(
                    $estadoGeneral
                ) ?>

            </h2>


            <p>

                <?= htmlspecialchars(
                    $mensajeEstadoGeneral
                ) ?>

            </p>

        </div>


        <div
            style="
                font-size:
                3rem;
            "
        >

            <?php

            if (
                !$estacionOnline
            ) {

                echo "📡";

            } elseif (
                $estadoGeneral ===
                "ALERTA"
            ) {

                echo "🔴";

            } elseif (
                $estadoGeneral ===
                "PRECAUCIÓN"
            ) {

                echo "🟡";

            } else {

                echo "🟢";
            }

            ?>

        </div>


    </section>


    <!-- ====================================================
         ÚLTIMA MEDICIÓN
         ==================================================== -->

    <section class="actualizacion">

        <h2>

            <?=
            $estacionOnline
            ? "Datos actuales"
            : "Última medición registrada"
            ?>

        </h2>


        <p>

            Fecha y hora:

            <strong>

                <?=
                htmlspecialchars(
                    $dato[
                        "fecha_hora"
                    ]
                )
                ?>

            </strong>

        </p>


        <small>

            La página se actualiza
            automáticamente cada 15 segundos.

        </small>

    </section>


    <!-- ====================================================
         TARJETAS DE SENSORES
         ==================================================== -->

    <section class="tarjetas">


        <!-- TEMPERATURA DHT11 -->

        <article
            class="
                tarjeta
                <?=
                !$estacionOnline
                ? "tarjeta-dato-viejo"
                : ""
                ?>
            "
        >

            <div class="icono">

                🌡️

            </div>

            <h3>

                Temperatura DHT11

            </h3>

            <div class="valor">

                <?=
                number_format(
                    (float)$dato[
                        "temperatura_dht"
                    ],
                    1
                )
                ?>

                °C

            </div>

            <div class="descripcion">

                <?=
                $estacionOnline
                ? "Temperatura ambiente"
                : "Último valor registrado"
                ?>

            </div>

        </article>


        <!-- HUMEDAD -->

        <article
            class="
                tarjeta
                <?=
                !$estacionOnline
                ? "tarjeta-dato-viejo"
                : ""
                ?>
            "
        >

            <div class="icono">

                💧

            </div>

            <h3>

                Humedad ambiente

            </h3>

            <div class="valor">

                <?=
                number_format(
                    (float)$dato[
                        "humedad_ambiente"
                    ],
                    1
                )
                ?>

                %

            </div>


            <div class="barra">

                <span
                    style="
                        width:
                        <?=
                        min(
                            100,
                            max(
                                0,
                                (float)$dato[
                                    "humedad_ambiente"
                                ]
                            )
                        )
                        ?>%;
                    "
                ></span>

            </div>


            <div class="descripcion">

                <?=
                $estacionOnline
                ? "Humedad relativa"
                : "Último valor registrado"
                ?>

            </div>

        </article>


        <!-- LLUVIA -->

        <article
            class="
                tarjeta
                <?=
                !$estacionOnline
                ? "tarjeta-dato-viejo"
                : ""
                ?>
            "
        >

            <div class="icono">

                🌧️

            </div>

            <h3>

                Estado de lluvia

            </h3>

            <div class="valor">

                <?=
                htmlspecialchars(
                    $dato[
                        "estado_lluvia"
                    ]
                )
                ?>

            </div>


            <div class="descripcion">

                Valor sensor:

                <?=
                (int)$dato[
                    "valor_agua"
                ]
                ?>

            </div>

        </article>


        <!-- LUMINOSIDAD -->

        <article
            class="
                tarjeta
                <?=
                !$estacionOnline
                ? "tarjeta-dato-viejo"
                : ""
                ?>
            "
        >

            <div class="icono">

                ☀️

            </div>

            <h3>

                Luminosidad

            </h3>

            <div class="valor">

                <?=
                (int)$dato[
                    "luminosidad"
                ]
                ?>

            </div>

            <div class="descripcion">

                <?=
                interpretarLuz(
                    (int)$dato[
                        "luminosidad"
                    ]
                )
                ?>

                <?php
                if (
                    !$estacionOnline
                ):
                ?>

                    · Último valor

                <?php endif; ?>

            </div>

        </article>


        <!-- LM35 -->

        <article
            class="
                tarjeta
                <?=
                !$estacionOnline
                ? "tarjeta-dato-viejo"
                : ""
                ?>
            "
        >

            <div class="icono">

                🌡️

            </div>

            <h3>

                Temperatura LM35

            </h3>

            <div class="valor">

                <?=
                number_format(
                    (float)$dato[
                        "temperatura_lm35"
                    ],
                    1
                )
                ?>

                °C

            </div>

            <div class="descripcion">

                <?=
                $estacionOnline
                ? "Sensor auxiliar de temperatura"
                : "Último valor registrado"
                ?>

            </div>

        </article>


    </section>


    <!-- ====================================================
         ASISTENTE CLIMÁTICO
         ==================================================== -->

    <section class="asistente">


        <h2>

            🤖 Asistente Climático SMART

        </h2>


        <?php if ($estacionOnline): ?>


            <p class="mensaje">

                <?=
                htmlspecialchars(
                    $mensajeClima
                )
                ?>

            </p>


            <div class="datos-asistente">


                <!-- LLUVIA -->

                <div class="caja-asistente">

                    <strong>

                        🌧 Probabilidad local
                        de lluvia

                    </strong>


                    <span
                        class="<?=
                            claseLluvia(
                                $probabilidadLluvia
                            )
                        ?>"
                    >

                        <?=
                        htmlspecialchars(
                            $probabilidadLluvia
                        )
                        ?>

                    </span>

                </div>


                <!-- CALOR -->

                <div class="caja-asistente">

                    <strong>

                        ☀ Nivel de calor

                    </strong>


                    <span
                        class="<?=
                            claseCalor(
                                $estadoCalor
                            )
                        ?>"
                    >

                        <?=
                        htmlspecialchars(
                            $estadoCalor
                        )
                        ?>

                    </span>

                </div>


                <!-- TEMP -->

                <div class="caja-asistente">

                    <strong>

                        🌡 Tendencia temperatura

                    </strong>


                    <?=
                    htmlspecialchars(
                        $tendenciaTemp
                    )
                    ?>

                </div>


                <!-- HUMEDAD -->

                <div class="caja-asistente">

                    <strong>

                        💧 Tendencia humedad

                    </strong>


                    <?=
                    htmlspecialchars(
                        $tendenciaHumedad
                    )
                    ?>

                </div>


                <!-- LUZ -->

                <div class="caja-asistente">

                    <strong>

                        ☀ Tendencia luminosidad

                    </strong>


                    <?=
                    htmlspecialchars(
                        $tendenciaLuz
                    )
                    ?>

                </div>


            </div>


        <?php else: ?>


            <p class="mensaje">

                🔴 La estación se encuentra
                actualmente desconectada.

                <br><br>

                El asistente climático ha
                suspendido las predicciones
                porque no dispone de nuevas
                mediciones en tiempo real.

                <br><br>

                Cuando SMART AGRO WEATHER vuelva
                a enviar datos, el análisis
                climático se reanudará
                automáticamente.

            </p>


        <?php endif; ?>


        <div class="nota">

            <strong>

                Importante:

            </strong>

            Las estimaciones de SMART AGRO WEATHER
            se basan en mediciones realizadas por
            los sensores locales y en el análisis
            de tendencias recientes.

            No reemplazan los pronósticos emitidos
            por organismos meteorológicos oficiales.

        </div>


    </section>


    <!-- ====================================================
         FOOTER
         ==================================================== -->

    <footer>

        <strong>

            SMART AGRO WEATHER v1.1

        </strong>

        <br><br>

        Colegio Sagrada Familia
        · Añatuya
        · Santiago del Estero

        <br>

        Feria de Ciencias 2026

        <br><br>

        Plataforma inteligente para monitoreo ambiental,
        análisis climático y agricultura de precisión.

    </footer>


</div>


<script>

/* ==========================================================
   MODO OSCURO
   ========================================================== */

function alternarModoOscuro()
{

    document.body.classList.toggle(
        "modo-oscuro"
    );


    if (
        document.body.classList.contains(
            "modo-oscuro"
        )
    ) {

        localStorage.setItem(
            "modoOscuro",
            "si"
        );

    } else {

        localStorage.setItem(
            "modoOscuro",
            "no"
        );

    }

}


/* ==========================================================
   RECORDAR MODO
   ========================================================== */

if (
    localStorage.getItem(
        "modoOscuro"
    ) === "si"
) {

    document.body.classList.add(
        "modo-oscuro"
    );

}

</script>


</body>

</html>