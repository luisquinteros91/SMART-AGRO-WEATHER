import os
import time
import serial
import requests

PUERTO = os.getenv("SMART_AGRO_PORT", "COM4")
BAUDIOS = 9600
URL = os.getenv(
    "SMART_AGRO_URL",
    "https://TU_DOMINIO/smart_agro_weather/recibir_datos.php"
)
API_KEY = os.getenv("SMART_AGRO_API_KEY", "")

if not API_KEY:
    raise RuntimeError("Defina SMART_AGRO_API_KEY antes de ejecutar el programa.")

print("SMART AGRO WEATHER")
print(f"Conectando con Arduino en {PUERTO}...")

arduino = serial.Serial(PUERTO, BAUDIOS, timeout=2)
time.sleep(2)

print("Arduino conectado.")
print("Esperando mediciones...")

while True:
    try:
        linea = arduino.readline().decode("utf-8", errors="ignore").strip()
        if not linea:
            continue

        # Arduino envía:
        # temperaturaDHT,humedad,agua,luz,temperaturaLM35
        partes = linea.split(",")
        if len(partes) != 5:
            print("Línea ignorada:", linea)
            continue

        temperatura, humedad, agua, luz, lm35 = partes

        datos = {
            "api_key": API_KEY,
            "temperatura_dht": temperatura,
            "humedad_ambiente": humedad,
            "valor_agua": agua,
            "luminosidad": luz,
            "temperatura_lm35": lm35,
        }

        respuesta = requests.post(URL, data=datos, timeout=10)
        print("Arduino:", linea)
        print("Servidor:", respuesta.status_code, respuesta.text)
        print("-----------------------------")
        time.sleep(1)

    except KeyboardInterrupt:
        print("\nPrograma detenido.")
        arduino.close()
        break
    except Exception as error:
        print("ERROR:", error)
        time.sleep(3)
