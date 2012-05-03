(function(window, $) {
  var arduino = new Comet();  

  var ready = function() {
    this.sendObject([{     
      command: 'pinmode',
      pin: 'A0',
      value: 'input'
    }], function() {
      this.sendObject([{
        command: 'readAnalog',
        pin: 'A0'
      }], function(value) {
        console.log(value);
      });
    });    
  };

  arduino.open({
    url: 'comet-router.php',
    recvAction: 'get_ar_data',
    sendAction: 'put_web_data',
    onRecieve: function(data) {
      console.log(data);
      return (data != 'TMOUT');
    },
    ready: ready
  });
}(this, jQuery));