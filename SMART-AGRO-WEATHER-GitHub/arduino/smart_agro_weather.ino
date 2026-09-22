/*
  SMART AGRO WEATHER - VERSIÓN INICIAL

  Componentes:
  - Arduino Uno
  - DHT11
  - Sensor Water
  - CDS / LDR
  - LM35
  - LCD 1602 I2C

  Datos enviados por USB:
  temperaturaDHT,humedad,agua,luz,temperaturaLM35

  Ejemplo:
  28.50,64.00,120,730,27.80
*/

#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>

// ----------------------------------------------------
// CONFIGURACIÓN DE PINES
// ----------------------------------------------------

#define PIN_DHT 2
#define TIPO_DHT DHT11

const byte PIN_WATER = A0;
const byte PIN_LDR   = A1;
const byte PIN_LM35  = A2;

// ----------------------------------------------------
// CONFIGURACIÓN DE DISPOSITIVOS
// ----------------------------------------------------

// Dirección habitual del LCD I2C.
// Si no muestra nada, probar con 0x3F.
LiquidCrystal_I2C lcd(0x27, 16, 2);

DHT dht(PIN_DHT, TIPO_DHT);

// ----------------------------------------------------
// TIEMPOS
// ----------------------------------------------------

const unsigned long INTERVALO_LECTURA = 5000;
const unsigned long INTERVALO_PANTALLA = 3000;

unsigned long ultimaLectura = 0;
unsigned long ultimoCambioPantalla = 0;

// ----------------------------------------------------
// VARIABLES DE LOS SENSORES
// ----------------------------------------------------

float temperaturaDHT = 0.0;
float humedad = 0.0;
float temperaturaLM35 = 0.0;

int valorAgua = 0;
int valorLuz = 0;

byte pantallaActual = 0;
bool datosDHTValidos = false;

// ----------------------------------------------------
// CONFIGURACIÓN INICIAL
// ----------------------------------------------------

void setup() {
  Serial.begin(9600);

  dht.begin();

  lcd.init();
  lcd.backlight();

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("SMART AGRO");
  lcd.setCursor(0, 1);
  lcd.print("WEATHER");

  delay(2000);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Iniciando...");
  lcd.setCursor(0, 1);
  lcd.print("Sensores");

  delay(1500);

  leerSensores();
  mostrarPantalla();
}

// ----------------------------------------------------
// CICLO PRINCIPAL
// ----------------------------------------------------

void loop() {
  unsigned long tiempoActual = millis();

  if (tiempoActual - ultimaLectura >= INTERVALO_LECTURA) {
    ultimaLectura = tiempoActual;

    leerSensores();
    enviarDatosSerial();
  }

  if (
    tiempoActual - ultimoCambioPantalla >=
    INTERVALO_PANTALLA
  ) {
    ultimoCambioPantalla = tiempoActual;

    pantallaActual++;

    if (pantallaActual > 3) {
      pantallaActual = 0;
    }

    mostrarPantalla();
  }
}

// ----------------------------------------------------
// LEER TODOS LOS SENSORES
// ----------------------------------------------------

void leerSensores() {
  float nuevaHumedad = dht.readHumidity();
  float nuevaTemperatura = dht.readTemperature();

  if (
    !isnan(nuevaHumedad) &&
    !isnan(nuevaTemperatura)
  ) {
    humedad = nuevaHumedad;
    temperaturaDHT = nuevaTemperatura;
    datosDHTValidos = true;
  } else {
    datosDHTValidos = false;
  }

  valorAgua = analogRead(PIN_WATER);
  valorLuz = analogRead(PIN_LDR);

  temperaturaLM35 = leerTemperaturaLM35();
}

// ----------------------------------------------------
// LEER LM35 CON PROMEDIO
// ----------------------------------------------------

float leerTemperaturaLM35() {
  const byte CANTIDAD_MUESTRAS = 10;

  long sumaLecturas = 0;

  for (byte i = 0; i < CANTIDAD_MUESTRAS; i++) {
    sumaLecturas += analogRead(PIN_LM35);
    delay(5);
  }

  float promedio =
    sumaLecturas / (float)CANTIDAD_MUESTRAS;

  /*
    Arduino Uno:
    ADC de 10 bits: valores entre 0 y 1023.
    Referencia aproximada: 5 V.
    LM35: 10 mV por cada grado Celsius.
  */

  float voltaje = promedio * (5.0 / 1023.0);
  float temperatura = voltaje * 100.0;

  return temperatura;
}

// ----------------------------------------------------
// ENVIAR DATOS A PYTHON POR USB
// ----------------------------------------------------

void enviarDatosSerial() {
  /*
    No agregar textos en esta salida.
    Python espera exactamente cinco valores
    separados por comas.
  */

  if (!datosDHTValidos) {
    return;
  }

  Serial.print(temperaturaDHT, 2);
  Serial.print(",");

  Serial.print(humedad, 2);
  Serial.print(",");

  Serial.print(valorAgua);
  Serial.print(",");

  Serial.print(valorLuz);
  Serial.print(",");

  Serial.println(temperaturaLM35, 2);
}

// ----------------------------------------------------
// MOSTRAR DATOS EN EL LCD
// ----------------------------------------------------

void mostrarPantalla() {
  lcd.clear();

  switch (pantallaActual) {

    case 0:
      mostrarTemperaturaHumedad();
      break;

    case 1:
      mostrarLluvia();
      break;

    case 2:
      mostrarLuminosidad();
      break;

    case 3:
      mostrarComparacionTemperaturas();
      break;
  }
}

// ----------------------------------------------------
// PANTALLA 1: TEMPERATURA Y HUMEDAD
// ----------------------------------------------------

void mostrarTemperaturaHumedad() {
  lcd.setCursor(0, 0);
  lcd.print("Temp: ");

  if (datosDHTValidos) {
    lcd.print(temperaturaDHT, 1);
    lcd.print((char)223);
    lcd.print("C");
  } else {
    lcd.print("ERROR");
  }

  lcd.setCursor(0, 1);
  lcd.print("Humedad: ");

  if (datosDHTValidos) {
    lcd.print(humedad, 0);
    lcd.print("%");
  } else {
    lcd.print("ERROR");
  }
}

// ----------------------------------------------------
// PANTALLA 2: SENSOR WATER
// ----------------------------------------------------

void mostrarLluvia() {
  lcd.setCursor(0, 0);
  lcd.print("Agua: ");
  lcd.print(valorAgua);

  lcd.setCursor(0, 1);
  lcd.print(estadoLluvia(valorAgua));
}

// ----------------------------------------------------
// PANTALLA 3: LUMINOSIDAD
// ----------------------------------------------------

void mostrarLuminosidad() {
  lcd.setCursor(0, 0);
  lcd.print("Luz: ");
  lcd.print(valorLuz);

  lcd.setCursor(0, 1);
  lcd.print(estadoLuminosidad(valorLuz));
}

// ----------------------------------------------------
// PANTALLA 4: COMPARACIÓN DE TEMPERATURA
// ----------------------------------------------------

void mostrarComparacionTemperaturas() {
  lcd.setCursor(0, 0);
  lcd.print("DHT: ");

  if (datosDHTValidos) {
    lcd.print(temperaturaDHT, 1);
    lcd.print((char)223);
  } else {
    lcd.print("ERR");
  }

  lcd.setCursor(0, 1);
  lcd.print("LM35: ");
  lcd.print(temperaturaLM35, 1);
  lcd.print((char)223);
}

// ----------------------------------------------------
// INTERPRETACIÓN INICIAL DEL SENSOR DE AGUA
// ----------------------------------------------------

const char* estadoLluvia(int lectura) {
  /*
    Estos límites son iniciales.
    Después deberemos calibrarlos según
    las lecturas reales de tu sensor.
  */

  if (lectura < 100) {
    return "Sin lluvia";
  }

  if (lectura < 400) {
    return "Gotas";
  }

  return "Lluvia detect.";
}

// ----------------------------------------------------
// INTERPRETACIÓN INICIAL DEL LDR
// ----------------------------------------------------

const char* estadoLuminosidad(int lectura) {
  /*
    Según la forma de conexión del divisor,
    los valores pueden quedar invertidos.
  */

  if (lectura < 250) {
    return "Luz baja";
  }

  if (lectura < 700) {
    return "Luz media";
  }

  return "Luz alta";
}