(function(window, $) {

  var pinToTemperature = function(value) {
    var voltage = Arduino.map(value, 0, 1024, 0, 5);
    return (voltage - .3) * 1000 / 20;
  };    

  var getPOTValue = function(value) {    
    return Arduino.map(value, 0, 1024, 10, 32);
  };

  var tempGauge = new RoundGauge({
    el: $('#temperature')[0]
  });

  tempGauge.render();
  $('body').append(tempGauge.el);

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
        [2, 'output']
      ])
      .digitalWrite(2, Arduino.LOW)
      .flush().done(function() {
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
            .analogRead('A0', function(r) { console.log('THIS RESULT ', r); })
            .analogRead('A5')
            .flush().done(function(results) {
              var tmp = results[0],
              pot = results[1],
              temperature = pinToTemperature(tmp);

              $('#temp-readout').html(Math.round(temperature)+'&deg;C');

              tempGauge.setValue(temperature);
              tempGauge.setCriticals(getPOTValue(pot));

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