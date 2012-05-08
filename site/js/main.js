(function(window, $) {
  var arduino = new Comet();  

  var pinToTemperature = function(value) {
    // Map(pin, 0, 1024 0, 5)
    var voltage = value * 0.004887586;        
    return (voltage - .3) * 1000 / 20;
  };

  var getPOTValue = function(value) {    
    return (value - 0) * (32 - 0) / (1024 - 0) + 15;
  };

  var ready = function() {
    var commands = [{     
      command: 'pinMode',
      pin: 'A0',
      mode: 'input'
    }, {
      command: 'pinMode', 
      pin: 'A5',
      mode: 'input'
    }, {
      command: 'pinMode',
      pin: 2,
      mode: 'output'
    }]
    this.sendObject(commands, function(results) {
      console.log(results);

      var me = this;

      $('#r-button').click(function() {
        var $this = $(this);
        me.sendObject([{
          command: 'digitalWrite',
          pin: 2,
          value: +!$this.hasClass('on')
        }], function() {
          $this.toggleClass('on');
        })
      });

      (function read() {
        me.sendObject([{
          command: 'analogRead',
          pin: 'A0'
        }, {
          command: 'analogRead',
          pin: 'A5'
        }], function(value) {
          console.log(value);
          var tmp = value[0],
          pot = value[1];

          $('#temperature').html(pinToTemperature(tmp)+' degrees');
          $('#pot-val').html(getPOTValue(pot));

          setTimeout(read, 1000);
        });
      }());
    });    
  };

  arduino.open({
    url: 'ajax/{action}/',
    recvAction: 'get_ar_data',
    sendAction: 'put_web_data',
    onRecieve: function(data) {
      console.log(data);
      return (data != 'TMOUT');
    },
    ready: ready
  });
}(this, jQuery));