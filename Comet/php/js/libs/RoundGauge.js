/* global window */
(function(window, M) {
  "use strict";

    // Feature detects checks
    var features = {
      canvas: (!M && (function() {
        // TODO: canvas feature detect
        return true;
      })) || M.canvas
    };

    // shim layer with setTimeout fallback
    window.requestAnimFrame = (function(){
      return  window.requestAnimationFrame       || 
              window.webkitRequestAnimationFrame || 
              window.mozRequestAnimationFrame    || 
              window.oRequestAnimationFrame      || 
              window.msRequestAnimationFrame     || 
              function( callback ){
                window.setTimeout(callback, 1000 / 60);
              };
    })();

   var CanvasHelper = {
    PIx2: Math.PI * 2,

    wedge: function(ctx, x, y, r, startAngle, endAngle, antiClockwise) {
      ctx.beginPath();
      ctx.moveTo(x,y);
      ctx.arc(x, y, r, startAngle, endAngle, !!antiClockwise);
      ctx.lineTo(x,y);
      ctx.closePath();
      
      return ctx;
    },

    circle: function(ctx, x, y, r) {
      ctx.beginPath();
      ctx.arc(x, y, r, 0, this.PIx2);
      ctx.closePath();
      return ctx;
    },

    radToDeg: function(rad) {
      return rad * (180.0 / Math.PI);
    }
  };

  var shallowExtend = function(dest, src) {
    for (var property in src) {
      dest[property] = src[property];
    }
    return dest;
  };

  var RoundGaugeView = function(options) {
    this.options = options || {};
    this.initialize();
  };

  RoundGaugeView.prototype = {
    el: null, 

    faceCanvas: null,
    handCanvas: null,
    criticalCanvas: null,

    width: 0,
    height: 0,

    _noOfNicks: 0,
    _radiansPerNick: 0,
    _zeroNick: 0,

    _prefixedTransform: '',

    _initialDrawingComplete: false,

    initialize: function() {

      this.options = shallowExtend({
        id: null,
        css: null,
        classes: null,

        el: null,

        minLimit: 0,
        maxLimit: 100,

        minCritical: false,
        minCriticalStyle: 'rgba(255,0,0,.55)',
        maxCritical: false,
        maxCriticalStyle: 'rgba(255,0,0,.55)',

        value: 0,

        units: '',
        unitFont: '10pt Arial',
        valueReadOut: true,

        width: 400,
        height: 400,

        handTransition: false,

        scaleIncrement: 1,
        scaleStartAngle: Math.PI / 4,
        scaleSpanAngle: Math.PI * 1.5,
        scaleFont: '8pt Arial'
      }, this.options);

      this.el = (!this.options.el) ?
        window.document.createElement('div') :
        this.options.el;

      if (this.options.id) {
        this.el.id = this.options.id;
      }

      this.value = this.options.value;
    },

    frames:0,

    render: function() {
      if (!this._initialDrawingComplete) {
        this.initDrawing();
      }
      
      this.frames++;            

      window.requestAnimFrame(this.moveHand.bind(this));
      
      return this;
    },

    initDrawing: function() {
      var options = this.options;

      this.faceCanvas = this._createCanvas('face');

      if (options.width) {
        this.el.style.width = (this.faceCanvas.width = options.width)+'px';
      }

      if (options.height) {
        this.el.style.height = (this.faceCanvas.height = options.height)+'px';
      }
      
      this.width = this.faceCanvas.width;
      this.height = this.faceCanvas.height;

      this._noOfNicks = Math.round(options.maxLimit - options.minLimit) / 
        this.options.scaleIncrement;

      this._radiansPerNick = options.scaleSpanAngle / this._noOfNicks;

      this._zeroNick = (options.minLimit < 0) ? 
        Math.abs(options.minLimit) / options.scaleIncrement : 0;

      this._prefixedTransform = M.prefixed('Transform');

      var ctx = this.faceCanvas.getContext('2d');
      this.drawFace(ctx);
      this.drawScale(ctx);

      if (!this.handCanvas) {
        this.handCanvas = this._createHandCanvas();
        this.drawHand(this.handCanvas.getContext('2d'));
      }
      
      if (!this.criticalCanvas) {
        this.criticalCanvas = this._createCanvas('criticals');
        this.drawCriticals(this.criticalCanvas.getContext('2d'));
      }

      this._initialDrawingComplete = true;
    },

    drawFace: function(ctx) {
      var options = this.options;

      ctx.save();
      ctx.lineWidth = 1;
      ctx.fillStyle = 'white';

      CanvasHelper.circle(ctx,
        this.width / 2, this.height / 2, 
        Math.min(this.width, this.height) / 2 - 1
      );

      ctx.fill();
      ctx.stroke();

      // Inner face circle
      CanvasHelper.circle(ctx,
        this.width / 2, this.height / 2, 
        Math.min(this.width, this.height) / 7 - 1
      );

      ctx.stroke();

      ctx.fillStyle = 'black';

      CanvasHelper.circle(ctx,
        this.width / 2, this.height / 2, 
        Math.min(this.width, this.height) / 28 - 1
      );

      ctx.fill();
      ctx.stroke();

      // Units text
      if (options.units) {
        ctx.font = options.unitFont;
        var textWidth = ctx.measureText(options.units).width;
        ctx.fillText(options.units, this.width / 2 - textWidth / 2, this.height - 30);
      }

      ctx.restore();
    },
    
    drawCriticals: function(ctx) {
      var options = this.options;

      ctx.clearRect(0,0,this.width, this.height);

      if (options.maxCritical !== false) {
        ctx.save();
        ctx.fillStyle = options.maxCriticalStyle;
        ctx.lineWidth = 1;

        var maxAngle = this._valueToAngle(options.maxCritical);

        CanvasHelper.wedge(ctx,
          this.width / 2, this.height / 2,
          this.width / 2.3,
          maxAngle,  options.scaleStartAngle
        );
        
        ctx.fill();
        ctx.restore();
      }

      if (options.minCritical !== false) {
        ctx.save();
        ctx.fillStyle = options.minCriticalStyle;
        ctx.lineWidth = 1;

        var minAngle = this._valueToAngle(options.minCritical);

        CanvasHelper.wedge(ctx,
          this.width / 2, this.height / 2,
          this.width / 2.3,
          this._valueToAngle(options.minLimit), minAngle
        );
        
        ctx.fill();
        ctx.restore();
      }
    },

    drawScale: function(ctx) {
      var minDim = Math.min(this.width, this.height),
      options = this.options,
      scaleIndent = 10,
      noOfDecimals = (""+options.scaleIncrement).length - 2;

      if (noOfDecimals < 0) {
        noOfDecimals = 0;
      }

      ctx.save();

      // Transform canvas
      ctx.translate(this.width / 2, this.height / 2);
      ctx.rotate(options.scaleStartAngle - options.scaleSpanAngle);

      ctx.font = options.scaleFont;
      ctx.lineWidth = 1;

      var 
      valueInterval = Math.max(this._noOfNicks / 10, 1),
      value = options.minLimit,
      i = 0,
      x1 = this.width / 2 - scaleIndent, x2;

      while (i <= this._noOfNicks) {
        x2 = x1 - 10;

        if (i % valueInterval === 0) {
          var val = value.toFixed(noOfDecimals),
          textWidth = ctx.measureText(val).width;
          ctx.fillText(val, x2 - textWidth - 10, 5)
          x2 -= 5;
        }

        ctx.beginPath();
        ctx.moveTo(x1, 0);
        ctx.lineTo(x2, 0);
        ctx.closePath();
        ctx.stroke();

        ctx.rotate(this._radiansPerNick);

        value += options.scaleIncrement;
        i += 1;
      }

      ctx.restore();
    },
    
    drawHand: function(ctx) {
      ctx.lineWidth = 3;
      ctx.beginPath();
      ctx.moveTo(0, 3);
      ctx.lineTo(this.width / 2.3, 3);
      ctx.closePath();
      ctx.stroke();
    },

    moveHand: function() {
      var options = this.options,
      canvas = this.handCanvas;

      if (this.value > options.maxLimit) {
        this.value = options.maxLimit;
      } else if (this.value < options.minLimit) {
        this.value = options.minLimit;
      }

      var handAngle = this._valueToAngle(this.value) * (180 / Math.PI);

      canvas.style[this._prefixedTransform] =
        'rotate(' + handAngle + 'deg)';
    },

    setValue: function(value) {
      if (this.value === value) {
        return;
      }

      this.value = value;
      this.render();

      return this;
    },
    
    increment: function(i) {
      this.value += i;
      this.render();

      return this;
    },

    setCriticals: function(min, max) {
      var ctx = this.criticalCanvas.getContext('2d'),
      me = this;

      if (typeof min === 'object') {
        max = min.max;
        min = min.min;
      }

      if (min) {
        this.options.minCritical = min;
      }

      if (max) {
        this.options.maxCritical = max;
      }

      window.requestAnimationFrame(function() {
        me.drawCriticals(ctx);
      });

      return this;
    },

    _createHandCanvas: function() {
      var canvas = document.createElement('canvas');

      if (this.el.id) {
        canvas.id = this.el.id+'-hand';
      }
      canvas.width = this.width / 2;
      canvas.height = 6;
      canvas.style.zIndex = 1;
      canvas.style[M.prefixed('TransformOrigin')] = '0px '+(canvas.height / 2)+'px';
      if (M.csstransitions && this.options.handTransition) {
        canvas.style[M.prefixed('Transition')] = this.options.handTransition;
      }
      canvas.style.position = 'absolute';
      canvas.style.top = (this.height / 2 - (canvas.height / 2)) + 'px';
      canvas.style.left = (this.width / 2) + 'px';

      this.el.appendChild(canvas);
      return canvas;
    },

    _createCanvas: function(id) {
      var canvas = document.createElement('canvas');

      if (this.el.id) {
        canvas.id = this.el.id+'-'+id;
      }
      canvas.width = this.width;
      canvas.height = this.height;
      canvas.style.position = 'absolute';
      canvas.style.top = '0px';
      canvas.style.left = '0px';

      this.el.appendChild(canvas);
      return canvas;
    },

    _valueToAngle: function(value) {
      var options = this.options,
      pos = (value >= 0) ?
        value / options.scaleIncrement + this._zeroNick :
        Math.abs(value - options.minLimit) / options.scaleIncrement;

      return pos * this._radiansPerNick + 
        (options.scaleStartAngle - options.scaleSpanAngle);
    }
  };

  // Export
  window.RoundGauge = RoundGaugeView;
})(this, this.Modernizr /*(optional)*/);