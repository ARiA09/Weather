// INCLUDE LIBRARY
#ifdef ESP8266
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <pgmspace.h>
#else
#include <WiFi.h>
#include <HTTPClient.h>
#include <avr/pgmspace.h>
#endif

#include <time.h>
#include <PMS.h>

// CONFIG
static const char location[] PROGMEM = "LogA"; // Location
static const char hostName[] PROGMEM = "Weather-Station"; // Hostname For ESP
static const char ssid[] PROGMEM = "TEST"; // SSID to connect <TEST>
static const char password[] PROGMEM = "123456789"; // Password Wifi <123456789>
static const char hostAddress[] PROGMEM = "http://example.com"; // Host Address to Update data <http://example.com>
#define DEBUG_OUT Serial1 // GPIO2 (D4 pin on ESP-12E Development Board)
//#define DEBUG_OUT Serial //

// AIR SENSOR
volatile bool PMSRunning = false;
// PMS_READ_INTERVAL (00:45 min) and PMS_READ_DELAY (00:30 min)
// CAN'T BE EQUAL! Values are also used to detect sensor state.
static const uint32_t PMS_READ_INTERVAL = 45000;
static const uint32_t PMS_READ_DELAY = 30000;
// Takes N samples and counts the average
static const uint8_t PMS_READ_SAMPLES = 5;

// Default sensor state.
volatile uint32_t timerIntervalPMS = PMS_READ_DELAY;
PMS pms(Serial);

struct weatherData_t {
  uint16_t PM1;
  uint16_t PM2_5;
  uint16_t PM10;
  uint16_t O3;
  uint16_t CO;
  uint16_t SO2;
  uint16_t NO2;
};

const char* NTP_SERVER = "asia.pool.ntp.org";
const char* TZ_INFO    = "UTC-7";

tm timeinfo;
time_t now;
long unsigned lastNTPtime;
unsigned long lastEntryTime;

//#########################################################################################
// SETUP PHASE
void setup() {

  Serial.begin(9600);
  DEBUG_OUT.begin(9600);
  clearBuffer();

  // Switch to passive mode.
  pms.passiveMode();
  clearBuffer();

  pms.sleep();

  StartWiFi();
  delay(1000); // Wait for time services

  configTime(0, 0, NTP_SERVER);
  setenv("TZ", TZ_INFO, 1);

  if (getNTPtime(10)) {  // wait up to 10sec to sync
  } else {
    ESP.restart();
  }


  showTime(timeinfo);
  lastNTPtime = time(&now);
  lastEntryTime = millis();
}

//#########################################################################################
// LOOP PHASE
void loop() {
  getNTPtime(10);
  if (timeinfo.tm_min == 5 && timeinfo.tm_sec - 2 >= 0) {
    if (checkUpdatePMS()) {
      if (!PMSRunning) {
        readPMS();
      }
    }
  }
  delay(500);
}

//#########################################################################################
void StartWiFi() {
  /* Set the ESP to be a WiFi-client, otherwise by default, it acts as ss both a client and an access-point
      and can cause network-issues with other WiFi-devices on your WiFi-network. */
  WiFi.hostname(FPSTR(hostName));
  WiFi.mode(WIFI_OFF);        //Prevents reconnection issue (taking too long to connect)
  delay(1000);
  WiFi.mode(WIFI_STA);        //This line hides the viewing of ESP as wifi hotspot
  DEBUG_OUT.println("Connecting to: ");
  WiFi.begin(FPSTR(ssid), FPSTR(password));
  while (WiFi.status() != WL_CONNECTED ) {
    DEBUG_OUT.print(".");
    delay(500);
  }
}

//#########################################################################################
void postData(weatherData_t data) {

  delay(500);
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;    //Declare object of class HTTPClient
    String postData;
    postData = "address=" + String(FPSTR(location))
               + "&PM1=" + String(data.PM1)
               + "&PM2_5=" + String(data.PM2_5)
               + "&PM10=" + String(data.PM10)
               + "&O3=" + String(data.O3)
               + "&CO=" + String(data.CO)
               + "&SO2=" + String(data.SO2)
               + "&NO2=" + String(data.NO2);

    http.begin(String(FPSTR(hostAddress)) + "/api/weatherMonitors");              //Specify request destination
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");    //Specify content-type header

    int httpCode = http.POST(postData);   //Send the request
    delay(500);
    String payload = http.getString();    //Get the response payload

    DEBUG_OUT.println(httpCode);   //Print HTTP return code
    DEBUG_OUT.println(payload);    //Print request response payload

    http.end();  //Close connection
  }
}

//#########################################################################################
bool checkUpdatePMS() {

  bool Update = false;
  delay(500);
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;  //Object of class HTTPClient

    http.begin(String(FPSTR(hostAddress)) + "/api/weatherMonitors");              //Specify request destination
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");    //Specify content-type header
    http.begin(String(FPSTR(hostAddress)) + "/api/weatherMonitors/needUpdate");

    int httpCode = http.POST("address=" + String(FPSTR(location)));
    delay(500);
    //Check the returning code
    if (httpCode > 0) {
      //Check need to update PMS
      if (http.getString().toInt() == 0)
        Update = true;
    }
    http.end();   //Close connection
  }
  return Update;
}

//#########################################################################################
void readPMS()
{
  static uint32_t timerLastPMS = 0;
  uint32_t timerNowPMS = millis();
  if (timerNowPMS - timerLastPMS >= timerIntervalPMS) {
    timerLastPMS = timerNowPMS;
    timerCallback();
    timerIntervalPMS = timerIntervalPMS == PMS_READ_DELAY ? PMS_READ_INTERVAL : PMS_READ_DELAY;
  }
}

//#########################################################################################
void timerCallback() {
  if (timerIntervalPMS == PMS_READ_DELAY)
  {
    readPMSData();
    clearBuffer();
  }
  else
  {
    pms.wakeUp();
    clearBuffer();
  }
}

//#########################################################################################
void readPMSData()
{

  PMS::DATA currData;
  PMS::DATA avgData;
  weatherData_t wData;

  memset(&currData, 0, sizeof(currData));
  memset(&avgData, 0, sizeof(avgData));

  PMSRunning  = true;
  // Clear buffer (removes potentially old data) before read. Some data could have been also sent before switching to passive mode.
  clearBuffer();

  DEBUG_OUT.println("Send read request...");
  DEBUG_OUT.println("Reading data...");

  byte samplesTaken = 0;
  for (byte sampleIdx = 0; sampleIdx < PMS_READ_SAMPLES; sampleIdx++)
  {
    pms.requestRead();
    if (pms.readUntil(currData, 2000))
    {
      samplesTaken++;

      avgData.PM_AE_UG_1_0 += currData.PM_AE_UG_1_0;
      avgData.PM_AE_UG_2_5 += currData.PM_AE_UG_2_5;
      avgData.PM_AE_UG_10_0 += currData.PM_AE_UG_10_0;
    }
    delay(1000);
  }

  if (samplesTaken > 0)
  {
    avgData.PM_AE_UG_1_0 /= samplesTaken;
    avgData.PM_AE_UG_2_5 /= samplesTaken;
    avgData.PM_AE_UG_10_0 /= samplesTaken;

    wData.PM1 = avgData.PM_AE_UG_1_0;
    wData.PM2_5 = avgData.PM_AE_UG_2_5;
    wData.PM10 = avgData.PM_AE_UG_10_0;
    wData.O3 = 0;
    wData.CO = 0;
    wData.SO2 = 0;
    wData.NO2 = 0;
    postData(wData);
    PMSRunning  = false;
  }
  pms.sleep();
  clearBuffer();
}

//#########################################################################################
bool getNTPtime(int sec) {

  {
    uint32_t start = millis();
    do {
      time(&now);
      localtime_r(&now, &timeinfo);
      //  DEBUG_OUT.print(".");
      delay(10);
    } while (((millis() - start) <= (1000 * sec)) && (timeinfo.tm_year < (2019 - 1900)));
    if (timeinfo.tm_year <= (2019 - 1900))
      return false;  // the NTP call was not successful
    //    DEBUG_OUT.print("now ");
    //    DEBUG_OUT.println(now);
    char time_output[30];
    strftime(time_output, 30, "%a  %d-%m-%y %T", localtime(&now));
    //    DEBUG_OUT.println(time_output);
    //    DEBUG_OUT.println();
  }
  return true;
}

//#########################################################################################
void showTime(tm localTime) {
  DEBUG_OUT.print(localTime.tm_mday);
  DEBUG_OUT.print('/');
  DEBUG_OUT.print(localTime.tm_mon + 1);
  DEBUG_OUT.print('/');
  DEBUG_OUT.print(localTime.tm_year - 100);
  DEBUG_OUT.print('-');
  DEBUG_OUT.print(localTime.tm_hour);
  DEBUG_OUT.print(':');
  DEBUG_OUT.print(localTime.tm_min);
  DEBUG_OUT.print(':');
  DEBUG_OUT.print(localTime.tm_sec);
  DEBUG_OUT.print(" Day of Week ");
  if (localTime.tm_wday == 0)
    DEBUG_OUT.println(7);
  else
    DEBUG_OUT.println(localTime.tm_wday);
}


/*
  // Shorter way of displaying the time
  void showTime(tm localTime) {
  Serial.printf(
    "%04d-%02d-%02d %02d:%02d:%02d, day %d, %s time\n",
    localTime.tm_year + 1900,
    localTime.tm_mon + 1,
    localTime.tm_mday,
    localTime.tm_hour,
    localTime.tm_min,
    localTime.tm_sec,
    (localTime.tm_wday > 0 ? localTime.tm_wday : 7 ),
    (localTime.tm_isdst == 1 ? "summer" : "standard")
  );
  }
*/
//#########################################################################################
void clearBuffer() {
  do
  {
    Serial.read();
  }
  while (Serial.available() > 0);
}
