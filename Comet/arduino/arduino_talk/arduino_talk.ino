/* ARDUINO TALK - By Stanley Bondi */

#define DIGITAL 0
#define ANALOG 1
#define BOTH 2

String commandString = "";         // a string to hold incoming data
boolean stringComplete = false;  // whether the string is complete

int currentBeep = 0;
int currentBeepPin = 0;

int CHAROFFSET = 32;

void setup()
{
  // start serial port at 9600 bps:
  Serial.begin(9600);    
}

void loop()
{  
  if (stringComplete) {
    processCommand(commandString);
    
    commandString = "";
    stringComplete = false;
  }      
}

// Pin functions
int getModeFromCode(char code) {
  if (code == 'o') return OUTPUT;
  if (code == 'i') return INPUT;

  sendFail("Invalid pin mode");  
}

int getPinFromCode(char code, int pinType) {
  int pin = code - CHAROFFSET;
  if (pin < 0 || pin > ((pinType == DIGITAL) ? 13 : 19)) {
    sendFail("Pin out of range");
    return -1;
  }

  switch (pin - 14) {
  case 0:
    return A0;
  case 1:
    return A1;
  case 2:
    return A2;
  case 3:
    return A3;
  case 4:
    return A4;
  case 5:
    return A5;      
  }

  return pin;
}

// Response functions
void sendAck() {
  Serial.println("A");
}

void sendResponse(int value) {
  Serial.println("R"+String(value, DEC));
}

void sendFail(String msg) {
  Serial.println('F'+msg);
}

// Command stub functions
void _pinMode(int pin, int mode) {
  if (pin == -1 || mode == -1) { return; }

  pinMode(pin, mode);
}

void _pinWrite(int pin, int value, int pinType) {
  if (pin == -1) { return; }
  
  if (pinType == DIGITAL) {  
    digitalWrite(pin, (value == 0) ? LOW : HIGH);
  } else {
    analogWrite(pin, value);
  }
}

int _pinRead(int pin, int pinType) {
  if (pin == -1) { return -1; }

  if (pinType == DIGITAL) {  
   return digitalRead(pin);
  }
  
  return analogRead(pin);
}

void _beep(int pin, String args) {
  if (pin == -1) { return; } 
              
  int argIndex = args.indexOf('-');
  if (argIndex < 0) {
    sendFail("Insufficient arguments");
    return;
  }
  
  int freq = String(args.substring(0, argIndex)).toInt();
  int length = String(args.substring(argIndex + 1)).toInt();
  
  long delayValue = 1000000/freq/2;
  long numCycles = freq * length / 1000;
  for (long i=0; i < numCycles; i++){ 
      digitalWrite(pin,HIGH); 
      delayMicroseconds(delayValue);
      digitalWrite(pin,LOW); 
      delayMicroseconds(delayValue);
  }
}

// Command parsing and executing function
void processCommand(String data) {  
  char command = (data.charAt(0) - CHAROFFSET);      
  String args = data.substring(1);
  int resp;

  switch (command) {
    // Ping test
    case 0:
      sendAck();
    break;

    // pinMode
    case 1:    
      _pinMode(getPinFromCode(args.charAt(0), BOTH), 
        getModeFromCode(args.charAt(1)));      
      sendAck(); 
    break;
    
    // digitalWrite
    case 2:      
      _pinWrite(getPinFromCode(args.charAt(0), DIGITAL), 
        String(args.substring(1)).toInt(), DIGITAL);
      sendAck(); 
    break;
    
    // digitalRead
    case 3:
      resp = _pinRead(getPinFromCode(args.charAt(0), DIGITAL), DIGITAL);      
      
      if (resp == -1) { sendFail("Failed Read"); }      
      sendResponse(resp); 
    break;

   // analogWrite
   case 4:
      _pinWrite(getPinFromCode(args.charAt(0), ANALOG), 
        String(args.substring(1)).toInt(), ANALOG);    
      sendAck(); 
    break;
    
    // analogRead
    case 5:
      resp = _pinRead(getPinFromCode(args.charAt(0), ANALOG), ANALOG);      
      
      if (resp == -1) { sendFail("Failed Read"); }      
      sendResponse(resp); 
    break;
    
    // Specific
    // Buzz
    case 11:
      _beep(getPinFromCode(args.charAt(0), DIGITAL), args.substring(1));              
      sendAck(); 
    break;           
    
    default: sendFail("Unrecognised command "); break;
  }
}

void serialEvent() {
  while (Serial.available()) {
    char inChar = (char)Serial.read(); 

    if (inChar == '\n') {
      stringComplete = true;
      return;
    } 

    commandString += inChar;
  }
}
