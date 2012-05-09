(function(window, $) {

  var pinToTemperature = function(value) {
    var voltage = Arduino.map(value, 0, 1024, 0, 5);
    return (voltage - .3) * 1000 / 20;
  };    

  var getPOTValue = function(value) {    
    return Arduino.map(value, 0, 1024, 15, 32);
  };

  var arduino = new Arduino({
      comet: {
        url: 'comet-router.php?action={action}',
        recvAction: 'get_ar_data',
        sendAction: 'put_web_data',
      }
    });  

    var ready = function() {
      this.pinModes([
        ['A0', 'input'],
        ['A5', 'input'],
        ['2', 'output']
      ]).flush().done(function() {
          var self = this, redOn = false;

          $('#r-button').click(function() {
            var $this = $(this);
            redOn = !redOn;
            self
              .digitalWrite(2, redOn)
              .flush().done(function() {
                $this.toggleClass('on');
              });
          });

          (function read() {
            self
              .analogRead('A0')
              .analogRead('A5')
              .flush().done(function(results) {
                var tmp = results[0],
                pot = results[1];

                $('#temperature').html(pinToTemperature(tmp)+' degrees');
                $('#pot-val').html(getPOTValue(pot));

                setTimeout(read, 1000);
              });
          })();
      });

      this.on({
        receive: function(e, data) {
          console.log('Recv: ', data);
        },
        send: function(e, data) {
          console.log('Sent: ', data);        
        }
      });
    };   

    arduino.open(ready);
})(this, jQuery);