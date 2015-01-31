#include <WString.h>
#include <SPI.h>
#include <Ethernet.h>

/************************************************************************/
/* constants and definitions
/************************************************************************/

#define EOL char(0x1A)
#define MAX_INPUT_LENGTH 1024
#define NAN "nan"
#define DEBUG Serial.println(__LINE__);

uint8_t MAC[] = {0x90, 0xA2, 0xDA, 0x00, 0xFE, 0xBE};
const IPAddress LOCAL_IP(192, 168, 1, 177);
const IPAddress REMOTE_IP(173, 236, 228, 162);
const int port = 80;

const char *PDU_INITIALIZATION = "AT+CMGF=0";
const char *SMS_LENGTH_INITIALIZATION = "AT+CMGS=";
const char *CLEAR_SIM = "AT+CMGD=1,4";
const char *SIM_READY = "+SIND: 1";
const char *COMMAND_READY = "+SIND: 4";
const char *SMS_PREFIX = "+CMT:";
const char *SMS_REDIRECTION = "AT+CNMI=3,3,0,0";

namespace XML
{
const char *REQUEST = "request";
const char *GET_SMS = "getsms";
const char *PHONE_NUMBER = "phonenumber";
const char *MESSAGE = "message";
const char *DATA = "data";
const char *PDU = "pdu";
const char *SERVER = "server";
const char *CODE = "code";

String getOpenTag(const char *xml_)
{
String tag = String("<");
tag.concat(xml_);
tag.concat(">");

return tag;
}

String getCloseTag(const char *xml_)
{
String tag = String("</");
tag.concat(xml_);
tag.concat(">");

return tag;
}
};

/************************************************************************/
/* global variables
/************************************************************************/

int mInputCharCounter;
char mInputSerial[MAX_INPUT_LENGTH] = {0};
char mInputWeb[MAX_INPUT_LENGTH] = {0};
int mIsReady;
int mIsSMSExpected;
EthernetServer server(80);

/************************************************************************/
/* declarations
/************************************************************************/
void initialization();
void setup();
void loop();
void sendSMS(const String sms_);
void inputMessageObserver();
void outputMessageObserver();
bool isContain(const char *source_, const char *string_);
void sendSMSToServer();
void clearSerial1();

/************************************************************************/
/* implementations
/************************************************************************/

void initialization()
{
Serial.begin(9600);
Serial1.begin(9600);

Ethernet.begin(MAC, LOCAL_IP);
server.begin();

mInputCharCounter = 0;
mIsReady = 0;
mIsSMSExpected = 0;
}

void setup()
{
initialization();

// report
Serial.println("reset...");
}

void sendSMS(const String sms_)
{
if (mIsReady == 1)
{
// initialization of serial1 modem
Serial1.println(PDU_INITIALIZATION);

// set sms length
Serial1.print(SMS_LENGTH_INITIALIZATION);
Serial1.print( (sms_.length() - 2) / 2);
Serial1.println();

delay(500); // !!!!!!!!!!

// send sms
Serial1.print(sms_);
Serial1.println(EOL);

delay(1000);
}
}

void loop()
{
// read character
char ch = Serial1.read();
if (ch >= 0)
{
if (mInputCharCounter >= MAX_INPUT_LENGTH - 1)
{
mInputCharCounter = 0;
Serial.println("buffer overflow!");
}

// if end of line received
if (ch == '\n')
{
mInputSerial[mInputCharCounter] = '\0';
inputMessageObserver();

mInputCharCounter = 0;
}
else
{
mInputSerial[mInputCharCounter] = ch;
++mInputCharCounter;
}
}

listenWeb();
}

void inputMessageObserver()
{
Serial.println(mInputSerial);

do 
{
if (mIsSMSExpected == 1)
{
mIsSMSExpected = 0;

sendSMSToServer(mInputSerial);

break;
}

if (isContain(mInputSerial, SIM_READY) == 1)
{
break;
}

if (isContain(mInputSerial, COMMAND_READY) == 1)
{
Serial1.println(SMS_REDIRECTION);
Serial1.println(CLEAR_SIM);
mIsReady = 1;

Serial.println("ready...");

break;
}

if (isContain(mInputSerial, SMS_PREFIX) == 1)
{
mIsSMSExpected = 1;

break;
}

} while (false);
}

bool isContain(const char *source_, const char *string_)
{
int j = 0;
for (int i = 0; string_[j] != '\0'; ++i)
{
if (source_[i] == string_[j])
{
++j;
}
else
{
j = 0;
}

if (source_[i] == '\0')
{
break;
}
}

return string_[j] == '\0';
}

void sendSMSToServer(String pdu_)
{
Serial.println("send to server...");

EthernetClient client;
Ethernet.begin(MAC, LOCAL_IP);

if (client.connect(REMOTE_IP, port) > 0)
{
// build post
String post = "xml=";

post.concat(XML::getOpenTag(XML::DATA));
post.concat(XML::getOpenTag(XML::REQUEST));
post.concat(XML::getOpenTag(XML::GET_SMS));
post.concat(XML::getOpenTag(XML::PHONE_NUMBER));
post.concat(XML::getCloseTag(XML::PHONE_NUMBER));
post.concat(XML::getOpenTag(XML::MESSAGE));
post.concat(XML::getCloseTag(XML::MESSAGE));
post.concat(XML::getOpenTag(XML::PDU));
post.concat(pdu_);
post.concat(XML::getCloseTag(XML::PDU));
post.concat(XML::getCloseTag(XML::GET_SMS));
post.concat(XML::getCloseTag(XML::REQUEST));
post.concat(XML::getCloseTag(XML::DATA));

// send post with headers
client.println("POST /smsserver-update.php HTTP/1.1");
client.println("Host: myserverdomain.com");
client.println("Content-Type: application/x-www-form-urlencoded");
client.println("Connection: close");
client.print("Content-Length: ");

client.println(post.length());
client.println();

client.print(post);
client.println();

delay(200); // !!!!!!!!!!!
client.stop();

Serial.println("reported!");
}
}

void listenWeb()
{
EthernetClient client = server.available();

if (client != NULL)
{
if (client.connected() != 0)
{
Serial.println("url available...");

// read whole ulr line
int i = 0;
while (client.available() != 0)
{
char c = client.read();
mInputWeb[i] = c;
++i;

if (c == '\n')
{
mInputWeb[i] = '\0';
break;
}
}

// parse url
String pdu = String(mInputWeb);
int index = pdu.indexOf("p=");
if (index < 0)
{
Serial.println("valid parameter not found!");
client.stop();
return;
}

pdu = pdu.substring(index + 2);
index = pdu.indexOf(' ');
pdu = pdu.substring(0, index);

// send sms
Serial.println(String("send sms: ") + pdu);
sendSMS(pdu);

// send response
Serial.println("send response...");
client.println("HTTP/1.1 200 OK");
client.println("Content-Type: text/xml");
client.println();

client.println(XML::getOpenTag(XML::SERVER));
client.println(XML::getOpenTag(XML::CODE));
client.println("1");
client.println(XML::getCloseTag(XML::CODE));
client.println(XML::getCloseTag(XML::SERVER));

delay(200); // !!!!!!!!!!!!
client.stop();
}
}
}
