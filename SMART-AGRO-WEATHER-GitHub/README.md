# SMART AGRO WEATHER

Sistema IoT educativo para monitoreo ambiental y agrícola. Integra **Arduino, sensores, Python, PHP y MySQL** para adquirir mediciones, enviarlas a un servidor y visualizarlas desde una interfaz web.

## Arquitectura

```text
Sensores
   ↓
Arduino Uno
   ↓ USB / Serial
Python
   ↓ HTTP POST
API PHP
   ↓
MySQL
   ↓
Dashboard Web
```

## Sensores y hardware

- Arduino Uno
- DHT11: temperatura y humedad ambiente
- Sensor de agua/lluvia
- LDR: luminosidad
- LM35: temperatura analógica
- LCD 16x2 I2C

## Funcionalidades

- Lectura periódica de sensores.
- Visualización local de mediciones en LCD.
- Envío de datos desde Arduino por puerto serie.
- Script Python como puente entre Arduino y el servidor.
- API PHP protegida mediante clave privada.
- Almacenamiento de mediciones en MySQL.
- Dashboard web para consultar el estado de la estación.

## Tecnologías

**Arduino/C++ · Python · PHP · MySQL · HTML · CSS · IoT · HTTP**

## Estructura

```text
arduino/
  smart_agro_weather.ino
python/
  enviar_datos.py
  requirements.txt
web/
  index.php
  recibir_datos.php
  conexion.example.php
  estilos.css
  ...
.env.example
.gitignore
SECURITY.md
README.md
```

## Configuración

1. Copiar `web/conexion.example.php` como `web/conexion.php` y completar credenciales locales.
2. Instalar Python y ejecutar `pip install -r python/requirements.txt`.
3. Configurar `SMART_AGRO_API_KEY`, `SMART_AGRO_URL` y, si corresponde, `SMART_AGRO_PORT`.
4. Configurar en el servidor la misma variable `SMART_AGRO_API_KEY`.
5. Cargar `arduino/smart_agro_weather.ino` en el Arduino Uno.
6. Ejecutar `python/enviar_datos.py`.

## Seguridad

Las credenciales reales de MySQL y la clave privada de la estación no están incluidas en esta versión pública. No publique `web/conexion.php`, archivos `.env` ni claves de producción.

## Autor

**Luis Quinteros**  
Ingeniero en Sistemas · Desarrollador PHP/MySQL · Tecnología Educativa · Arduino/IoT · IA

LinkedIn: `linkedin.com/in/luis-quinteros-23897427`

## Estado

Proyecto de portfolio en evolución. Próximas mejoras posibles: calibración de sensores, histórico con gráficos, alertas, automatización de riego y despliegue IoT más autónomo.
