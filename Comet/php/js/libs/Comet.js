(function(window) {
  "use strict";
  
  var Events = function(context) {
    var events = {};  

    this.dispatch = function(id, obj) {
      var e = events[id];
      if (typeof e === 'undefined') { return; }

      var len = e.length,  
        i = 0;      

      for (;i < len;i++) {
        e[i].apply(context, [obj]);
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

  var Comet = window.Comet = function(options) {    
    options = options || {};

    if (typeof options.url === 'undefined') {
      throw new Error('url is required.');
    }

    if (!options.recvAction) { options.recvAction = 'get_data'; }
    if (!options.sendAction) { options.sendAction = 'put_data'; }

    // Private members
    var self = this,      
      events = new Events(this),
      service_started = false,
      incomingXHR = null;  

    var getNextId = function() {
      if (typeof window.Comet._staticId === 'undefined') {
        return window.Comet._staticId = 0;
      }

      return (++window.Comet._staticId % 32000);
    };

    // Public members
    this.onreceive = null;

    // Public methods
    this.startReceiving = function() {
      var xhr = incomingXHR = new XMLHttpRequest();
      service_started = true;      
      
      (function _service() {
        xhr.open('POST', this.getActionUrl(options.recvAction), true);

        xhr.onload = function(e) {
          if (this.status !== 200) {
            console.error(e);
            return;
          }

          var response = JSON.parse(this.response);
          if (!response) {
            console.error('Parse error: "'+this.response+'"');
            if (service_started) { setTimeout(_service.call(self), 0); };
            return;
          }

          if (response.state === 'error') {
            console.error(response.message);
            if (service_started) { setTimeout(_service.call(self), 0); };
            return;
          }

          if (response.result === 'TMOUT') {            
            if (service_started) { setTimeout(_service.call(self), 0); };
            return;
          }          

          if (typeof self.onreceive == 'function') {
            if (self.onreceive.apply(this, [response.result]) === false) {
              if (service_started) { setTimeout(_service.call(self), 0); };
              return;
            }
          }

          var results = response.result,
          len = results.length, i = 0;

          for(;i < len;i++) {
            events.dispatch(results[i].id, results[i]['object']);
          }

          if (service_started) { setTimeout(_service.call(self), 0); };
        };

        xhr.send(null);
      }.call(this))
    };       

    this.open = function(ready) {
      this.startReceiving();      

      if (typeof ready === 'function') {
        ready.call(this);
      }
    };

    this.close = function() {
      // Stop reciever
      service_started = false;
      incomingXHR && incomingXHR.abort();
      incomingXHR = undefined;
    };

    this.getActionUrl = function(action) {
      return options.url.replace('{action}', action);
    };

    this.sendObject = function(obj, complete) {
      var id = getNextId();      

      var data = new FormData;
      data.append('object', JSON.stringify({id: id, object: obj }));

      var xhr = new XMLHttpRequest();
      xhr.open('POST', this.getActionUrl(options.sendAction), true);
      xhr.onload = function() {
        var response = JSON.parse(this.response);
        if (response.state === 'error') {
          console.error('ERROR: ' + response.id + response.message);
          return;
        }

        if (response.result != 'PASS') {
          console.error('Recieved unknown result: '+ response)
          return;
        }        

        events.register(id, complete);
      };

      xhr.send(data);
    };
  };
}(this))