(function(window) {
  "use strict";
  
  var Events = function(context) {

    var events = {};  

    this.dispatch = function(id, args) {
      var e = events[id];
      if (typeof e === 'undefined') { return; }

      var len = e.length,  
        i = 0;

      args = args || [];

      for (;i < len;i++) {
        e[i].apply(context, args);
      }

      delete events[id];
    };

    this.register = function(id, callback) {
      if (typeof events[id] === 'undefined') {
        events[id] = [];
      }

      events[id].push(callback);
    };  
  };

  var Comet = window.Comet = function() {    
    // Private members
    var self = this,      
      events = new Events(this),
      service_started = false,
      incomingXHR = null;  

    var getNextId = function() {
      if (typeof window.Comet._staticId === 'undefined') {
        return window.Comet._staticId = 0;
      }

      return ++window.Comet._staticId;
    };

    // Public members
    this.options = null;
    
    // Public methods
    this.startReceiving = function() {
      var xhr = incomingXHR = new XMLHttpRequest();
      service_started = true;

      var data = new FormData;
      data.append('request', JSON.stringify({
        action: this.options.recvAction
      }));
      
      (function _service() {
        xhr.open('POST', this.options.url, true);

        xhr.onload = function(e) {
          if (this.status !== 200) {
            console.error(e);
            return;
          }

          var response = JSON.parse(this.response);
          if (response.state === 'error') {
            console.error(response.id, response.message);
            if (service_started) { setTimeout(_service.call(self), 0); };
            return;
          }

          if (typeof self.options.onRecieve == 'function') {
            if (self.options.onRecieve.apply(this, [response.result]) === false) {
              if (service_started) { setTimeout(_service.call(self), 0); };
              return;
            }
          }

          events.dispatch(response.result.id, [response.result]);

          if (service_started) { setTimeout(_service.call(self), 0); };
        };

        xhr.send(data);
      }.call(this))
    };       

    this.open = function(options) {
      options = options || {};

      if (typeof options.url === 'undefined') {
        throw new Error('url is required.');
      }

      if (!options.recvAction) { options.recvAction = 'get_data'; }
      if (!options.sendAction) { options.sendAction = 'put_data'; }

      this.options = options;
      this.startReceiving();      

      if (typeof options.ready === 'function') {
        options.ready.call(this);
      }
    };

    this.close = function() {
      // Stop reciever
      service_started = false;
      incomingXHR && incomingXHR.abort();
      incomingXHR = undefined;

      this.options = {};
    };

    this.sendObject = function(obj, complete) {
      var id = getNextId();

      events.register(id, complete);

      var data = new FormData;
      data.append('request', JSON.stringify({        
        action: this.options.sendAction, 
        args: { id: id, payload: obj}
      }));

      var xhr = new XMLHttpRequest();
      xhr.open('POST', this.options.url, true);
      xhr.send(data);
    };
  };
}(this))