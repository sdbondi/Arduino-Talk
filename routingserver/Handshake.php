<?php

interface HandshakeImpl {        
    function get_response(array $headers);
}

// http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-17
class WebSocket13Handshake implements HandshakeImpl {
    private $_guid;
        
    
    function __construct() {
        $this->_guid = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    }
    
    function get_hash($key) {
        return base64_encode(sha1($key.$this->_guid, true));
    }
    
    function get_response(array $headers) {         
        if (!isset($headers['Sec-WebSocket-Key']))
            throw new Exception('Sec-WebSocket-Key missing');
                              
        $hashstr = $this->get_hash($headers['Sec-WebSocket-Key']);        
        
        $response = sprintf(
            "HTTP/1.1 101 Switching Protocols\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".            
            "Sec-WebSocket-Accept: %s\r\n".
            "\r\n", $hashstr);
                    
        return $response;
    }
}


function create_handshake($version) {
    switch ($version) {
        case 13:
            return new WebSocket13Handshake();
        default:
            throw new Exception("Handshake version {$version} not implemented");
    }
}