String commandString = "";         // a string to hold incoming data
boolean stringComplete = false;  // whether the string is complete

int currentBeep = 0;
int currentBeepPin = 0;

int CHAROFFSET = 32;

void setup()
{
  // start serial port at 9600 bps:
  Serial.begin(9600);  
  pinMode(8, OUTPUT);
}

void loop()
{  
  if (stringComplete) {
    processCommand(commandString);
    
    commandString = "";
    stringComplete = false;
  }      
}

int getModeFromCode(char code) {
  if (code == 'o') return OUTPUT;
  if (code == 'i') return INPUT;
  return -1;
}

void sendAck(int msgId) {
  String response = "A ";
  response.setCharAt(1, msgId);  
  Serial.println(response);
}

void sendResponse(int msgId, int value) {
  String response = "R ";
  response.setCharAt(1, msgId);
  Serial.println(response+String(value, DEC));
}

void sendFail(String msg) {
  Serial.println('F'+msg);
}

int toAnalogPin(int pin) {
   switch (pin) {
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
   return -1;
}

void beep(int pin, long freq, long length) {
  long delayValue = 1000000/freq/2; // calculate the delay value between transitions
  //// 1 second's worth of microseconds, divided by the frequency, then split in half since
  //// there are two phases to each cycle
  long numCycles = freq * length / 1000; // calculate the number of cycles for proper timing
  //// multiply frequency, which is really cycles per second, by the number of seconds to 
  //// get the total number of cycles to produce
  for (long i=0; i < numCycles; i++){ // for the calculated length of time...
      digitalWrite(pin,HIGH); // write the buzzer pin high to push out the diaphram
      delayMicroseconds(delayValue); // wait for the calculated delay value
      digitalWrite(pin,LOW); // write the buzzer pin low to pull back the diaphram
      delayMicroseconds(delayValue); // wait againf or the calculated delay value
  }
}

void processCommand(String data) {
  int msgId = data.charAt(0);
  int pin = -1;
  String args;
  int arg0 = 0;
  int arg1 = 0;
  int arg2 = 0;
  int argIndex = -1;
  
  switch (data.charAt(1) - CHAROFFSET) {
    // Ping test
    case 1:
      sendAck(msgId);
    break;

    // pinMode
    case 2:    
      pin = data.charAt(2) - CHAROFFSET;
      arg0 = getModeFromCode(data.charAt(3));
      if (arg0 == -1) {
        sendFail("Invalid pin mode");
        return;  
      }

      if (pin < 0 || pin > 19) {
        sendFail("Pin out of range");
        return;
      }
      pinMode(pin, arg0);
      sendAck(msgId); 
    break;
    
    // digitalWrite
    case 3:
      pin = data.charAt(2) - CHAROFFSET;
      arg0 = String(data.substring(3)).toInt();
      if (pin < 0 || pin > 13) {
        sendFail("Pin out of range");
        return;
      }
      digitalWrite(pin, arg0);
      sendAck(msgId); 
    break;
    
    // digitalRead
    case 4:
      pin = data.charAt(2) - CHAROFFSET;
      if (pin < 0 || pin > 13) {
        sendFail("Pin out of range");
        return;
      }
      arg0 = digitalRead(pin);
      sendResponse(msgId, arg0); 
    break;

   // analogWrite
   case 5:
      pin = data.charAt(2) - CHAROFFSET - 14;
      arg0 = String(data.substring(3)).toInt();
      if (pin < 0 || pin > 5) {
        sendFail("Pin out of range");
        return;
      }
      analogWrite(toAnalogPin(pin), arg0);
      sendAck(msgId); 
    break;
    
    // analogRead
    case 6:
      pin = data.charAt(2) - CHAROFFSET - 14;
      if (pin < 0 || pin > 5) {
        sendFail("Pin out of range");
        return;
      }
                    
      arg0 = analogRead(toAnalogPin(pin));
      sendResponse(msgId, arg0); 
    break;
    
    // Specific
    // Buzz
    case 11:
      pin = data.charAt(2) - CHAROFFSET;
      if (pin < 0 || pin > 13) {
        sendFail("Pin out of range");
        return;
      }
              
      args = data.substring(3);    
      argIndex = args.indexOf('-');  
      if (argIndex < 0) {
        sendFail("Insufficient arguments");
        return;
      }
      arg0 = String(args.substring(0, argIndex)).toInt();
      arg1 = String(args.substring(argIndex + 1)).toInt();
      beep(pin, arg0, arg1);            
        
      sendAck(msgId); 
    break;           
    
    default:  beep(8, 440, 300); sendFail("Unrecognised command "); break;
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
