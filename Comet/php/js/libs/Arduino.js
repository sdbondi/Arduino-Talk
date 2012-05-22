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
    this.pinMode = function(pin, mode, callback) {
      commandBuffer.push({
        command: 'pinMode',
        pin: pin,
        // We want 'i' or 'o' for the pinmode
        args: [mode[0]],
        callback: callback        
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

    this.digitalWrite = function(pin, value, callback) {
      commandBuffer.push({
        command: 'digitalWrite',
        pin: pin,
        args: [value ? Arduino.HIGH : Arduino.LOW],
        callback: callback        
      });

      return this;
    };

    this.digitalRead = function(pin, callback) {
      commandBuffer.push({
        command: 'digitalRead',
        pin: pin,
        callback: callback
      });

      return this;
    };

    this.analogRead = function(pin, callback) {
      commandBuffer.push({
        command: 'analogRead',
        pin: pin,
        callback: callback              
      });

      return this;
    };

    this.analogWrite = function(pin, value, callback) {
      commandBuffer.push({
        command: 'analogWrite',
        pin: pin,
        args: [value],
        callback: callback              
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
      var callbacks = commandBuffer.map(function(o) { 
        var cb = o.callback;
        delete o.callback; // Note the side affect
        return cb;
      });

      this.comet.sendObject(commandBuffer, function(results) {
        var i = 0, len = callbacks.length;

        for (;i < len;i++) {
          var cb = callbacks[i];
          if (typeof cb === 'function') { cb.apply(self, [results[i]]); }
        }

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