(function() {
  var Arduino = function(options) {
    options = options || {};

    // Private
    var commandBuffer = [],
    self = this;

    var onReceive = function(results) {
      $(this).trigger('receive', [results]);
    };

    // Public members
    this.comet = new window.Comet(options.comet);
    this.comet.onreceive = onReceive.bind(this);

    // Public methods
    this.pinMode = function(pin, mode) {
      commandBuffer.push({
        command: 'pinMode',
        pin: pin,
        // We want 'i' or 'o' for the pinmode
        args: [mode[0]]        
      });

      return this;
    };

    this.pinModes = function(modes) {
      var len = modes.length,
      i = 0;

      for (;i < len;i++) {
        var mode = modes[i];
        this.pinMode(mode[0], mode[1]);
      }

      return this;
    };

    this.digitalWrite = function(pin, value) {
      commandBuffer.push({
        command: 'digitalWrite',
        pin: pin,
        args: [value ? Arduino.HIGH : Arduino.LOW]        
      });

      return this;
    };

    this.digitalRead = function(pin) {
      commandBuffer.push({
        command: 'digitalRead',
        pin: pin
      });

      return this;
    };

    this.analogRead = function(pin) {
      commandBuffer.push({
        command: 'analogRead',
        pin: pin       
      });

      return this;
    };

    this.analogWrite = function(pin, value) {
      commandBuffer.push({
        command: 'analogWrite',
        pin: pin,
        args: [value]        
      });

      return this;
    };    

    this.flush = function() {
      var deferred = $.Deferred();
      if (commandBuffer.length == 0) { 
        deferred.resolveWith(this, [[]]); 
        return deferred.promise();
      }

      $(this).trigger('send', [commandBuffer]);
      this.comet.sendObject(commandBuffer, function(results) {
        deferred.resolveWith(self, [results]);
      });

      commandBuffer = [];

      return deferred.promise();
    };

    this.on = function(events, handler) {
      $(this).on(events, handler);
    };

    this.off = function(events, handler) {
      $(this).off(events, handler);
    };

    this.open = function(ready) {
      this.comet.open(ready.bind(this));
    };

    this.close = function() {
      this.comet.close();
    };
  };

  $.extend(Arduino, {
      map: function(x, inMin, inMax, outMin, outMax) {
        return (x - inMin) * (outMax - outMin) / (inMax - inMin) + outMin;
      },
      HIGH: 1,
      LOW: 0
    });

  window.Arduino = Arduino;
})(this, jQuery);