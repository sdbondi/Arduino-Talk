# Arduino talk

A JSON-based API for Arduino with all the supporting software.

## About
A simple API to issue commands to your Arduino Microcontroller from you web browser though a simple JSON API interface. This is currently an early "planning"/POC release of this project and I'll probably end up rewriting it. Suggestions/forks welcome!

It does work quite well as it is now. I have used it to turn on relays and LEDS remotely (setting pinMode and digitalWrite). I also can poll (analogRead) from a temperature sensor.

The reason I used Comet (long AJAX requests) is because:
- All you need is PHP with shared memory enabled (Most hosting packages support this - could change to filesystem based IPC).
- All modernish mobile browsers support AJAX (TOFIX: Admitidly I've used HTML5 FormData which is only supported by Android 4+ browsers). 
- Allows clients on both ends to poll when they are ready - simple and reliable architecture.

I plan to implement a websocket version.

#Comet

##Basics
I'll draw a picture and put it here but for now I'll explain it simply. Using http requests only, I make AJAX requests to a PHP 'routing' script. I use comet techniques to allow data to be passed through as soon as it is received. The routing script uses shared memory to communicate between processes (requests).

There is a python server which also uses those same AJAX requests to get and send its data. It's responsible for translating the JSON-based commands to the succinct Arduino protocol as well as translating the results back to JSON.

It consists of 4 parts:

##Javascript API
I have implemented a comet client API and a sugary Arduino API
on top of that to make programming web interfaces for your projects
as easy as possible with a good knowledge of javascript.

An Example:
This will send 2 commands at once, run them and return both results
in one request. You can chain as many as you like before flushing.
```javascript
var arduino = new Arduino({comet:{url:'comet-router?action={action}'}});

arduino.open(function() {
  this
    .pinMode(2, 'output')
    .digitalWrite(2, 1)
    .pinMode('A0', 'input')
    .analogRead('A0')
    .flush(function(results) { console.log(results); })
});
```

*`results` could be ['ACK', 'ACK', 'ACK', 128]*

## PHP Command Router
Furnisheds requests from JS and Python and relays messages using
shared memory objects.

## Python server
Resposible for translating the JSON commands to commands the Arduino
understands. It connects to the Arduino through serial.

## Arduino code
Resposible for understanding the commands received and executing them.

## License

See the file [LICENSE](https://github.com/sdbondi/Arduino-Talk/blob/master/LICENSE.txt)

## TODO

- Make a real front end example
- Perhaps rethink PHP routing server as it seems limited/buggy
- Improve and document JS API
- Implement WebSockets
- crap load more...

