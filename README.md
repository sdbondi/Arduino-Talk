# Arduino talk (comet)

In this project I've created a json based api for an Arduino.
It is very much a work in progress. I have put this on GitHub 
so that it can be improved and recieve advice/feedback. This
project was quite a shot in the dark for me.

But hey, it works! And I plan to use it to allow me to control
some home appliances/lights/heaters and monitor various things 
remotely from anywhere in the world!

##Basics
I'll draw a picture and put it here but for now I'll explain 
it simply. Using http requests only, I make AJAX requests to a 
PHP 'routing' script. I use comet techniques to allow data to
be passed through as soon as it is received. The routing script
uses shared memory to communicate between AJAX requests.

There is a python server which also uses the same AJAX requests
to get and send its data. It's responsible for translating the 
JSON-based commands to my vastly less verbose Arduino protocol
as well as translating the results back to JSON.

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

##PHP Command Router
Furnisheds requests from JS and Python and relays messages using
shared memory objects.

##Python server
Resposible for translating the JSON commands to commands the Arduino
understands. It connects to the Arduino through serial.

##Arduino code
Resposible for understanding the commands received and executing them.

## License

See the file [LICENSE](https://github.com/sdbondi/Arduino-Talk/blob/master/LICENSE.txt)
