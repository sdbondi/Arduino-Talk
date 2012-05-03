<?php

require_once 'Helpers.php';
require_once 'Handshake.php';

define('BUFF_SIZE', 1024);

class WebSocket {    
    public $socket;
    public $address;
    public $port;
    
    function __construct($address, $port = 0) {        
        $this->address = $address;
        $this->port = $port;        
    }   
        
    private function _do_handshake() {                
        if (!isset($this->socket))            
            return false;                    
                
        $buffer = '';
        if (socket_recv($this->socket, $buffer, BUFF_SIZE, MSG_WAITALL) === false)             
            return false;        
        
        $headers = Helpers::parse_headers($buffer);        
        
        $handshaker = create_handshake(+$headers['Sec-WebSocket-Version']);        
        $response = $handshaker->get_response($headers);
        
        return (socket_write($this->socket, $response) !== false);       
    }
    
    function read($length) {
        $result = $this->hybi10Decode(socket_read($this->socket, $length));        
        return $result['payload'];
    }
    
    function write($data, $type = 'text', $masked = true) {
        return socket_write($this->socket, $this->hybi10Encode($data, $type, $masked));
    }
    
    function close($statusCode = false) {      
        if ($statusCode === false) {
            if ($this->socket)
                socket_close($this->socket);
            return;
        }
      
        $payload = str_split(sprintf('%016b', $statusCode), 8);
        $payload[0] = chr(bindec($payload[0]));
        $payload[1] = chr(bindec($payload[1]));
        $payload = implode('', $payload);

        switch($statusCode)
        {
            case 1000:
                $payload .= 'normal closure';
            break;

            case 1001:
                $payload .= 'going away';
            break;

            case 1002:
                $payload .= 'protocol error';
            break;

            case 1003:
                $payload .= 'unknown data (opcode)';
            break;

            case 1004:
                $payload .= 'frame too large';
            break;        

            case 1007:
                $payload .= 'utf8 expected';
            break;

            case 1008:
                $payload .= 'message violates server policy';
            break;
        }

        if($this->write($payload, 'close', false) === false)
        {
            return false;
        }
       
        socket_close($this->socket);
    }
    
    private function hybi10Encode($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $frame = '';
        $payloadLength = strlen($payload);

        switch($type)
        {        
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;                
            break;            

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
            break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
            break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
            break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes) 
        if($payloadLength > 65535)
        {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for($i = 0; $i < 8; $i++)
            {
                $frameHead[$i+2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if($frameHead[2] > 127)
            {
                echo 'Connection closed because frame was too big'."\n";
                $this->close(1004);
                return false;
            }
        }
        elseif($payloadLength > 125)
        {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        }
        else
        {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach(array_keys($frameHead) as $i)
        {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if($masked === true)
        {
            // generate a random mask:
            $mask = array();
            for($i = 0; $i < 4; $i++)
            {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);            
        }                        
        $frame = implode('', $frameHead);

        // append payload to frame:
        $framePayload = array();    
        for($i = 0; $i < $payloadLength; $i++)
        {        
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    private function hybi10Decode($data)
    {
        $payloadLength = '';
        $mask = '';
        $unmaskedPayload = '';
        $decodedData = array();

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));        
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength = ord($data[1]) & 127;

        // close connection if unmasked frame is received:
        if($isMasked === false)
        {
            echo 'Connection closed because unmasked frame was recieved'."\n";            
            $this->close(1002);
        }

        switch($opcode)
        {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';                
            break;

            case 2:
                $decodedData['type'] = 'binary';
            break;

            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
            break;

            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';                
            break;

            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
            break;

            default:
                // Close connection on unknown opcode:
                echo 'Connection closed because unknown opcode recieved'." ({$opcode})\n";
                $this->close(1003);
            break;
        }

        if($payloadLength === 126)
        {
           $mask = substr($data, 4, 4);
           $payloadOffset = 8;
           $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        }
        elseif($payloadLength === 127)
        {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for($i = 0; $i < 8; $i++)
            {
                $tmp .= sprintf('%08b', ord($data[$i+2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        }
        else
        {
            $mask = substr($data, 2, 4);    
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd. 
         */
        if(strlen($data) < $dataLength)
        {            
            return false;
        }

        if($isMasked === true)
        {
            for($i = $payloadOffset; $i < $dataLength; $i++)
            {
                $j = $i - $payloadOffset;
                if(isset($data[$i]))
                {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        }
        else
        {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }
    
    function establish_single() {
        // Create socket
        if (!($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) 
            throw new Exception('Unable to create socket');            
            
        if (!socket_bind($socket, $this->address, $this->port))
            throw new Exception('Unable to bind socket to '."{$this->address}:{$this->port}, ".socket_strerror());                        
                            
        if (!socket_listen($socket, SOMAXCONN)) 
            throw new Exception('Unable to listen on socket. '.socket_strerror());
        
        if (!($new_socket = socket_accept($socket)))
            throw new Exception('Unable to accept socket. '.socket_strerror());
        
        socket_close($socket);
        
        $this->socket =& $new_socket;
        
        if (!$this->_do_handshake())
            return false;
            
        return $this->socket;
    }    
}