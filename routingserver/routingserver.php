<?php
require_once 'KLogger.php';
require_once 'Helpers.php';
require_once 'WebSocket.php';

define('DEBUG', true);
define('LOGLEVEL', (DEBUG === true) ? KLogger::DEBUG : KLogger::ERROR);
declare(ticks = 1);

$router_opts = array(    
    'arduino_host' => '127.0.0.1',
    'arduino_port' => 8085,
    'sleep_wait' => 5000,
    'web_host' => '10.0.0.106',
    'web_port' => 8086
);

$router = null;
$is_running = false;
    
class RoutingServer {
    private $_log;
    private $_TAG = __CLASS__;
    private $_arduino_sock = null; 
    private $_web_sock = null;    
    private $_auth_key = '';
    
    public $arduino_host;
    public $arduino_port;
    public $sleep_wait;
    public $web_host;
    public $web_port;
    
    function __construct($options) {
        $this->_log = new KLogger("php://stdout", LOGLEVEL, __CLASS__);
        
        $this->arduino_host = $options['arduino_host'] or '127.0.0.1';
        $this->arduino_port = $options['arduino_port'] or 8081;
        $this->sleep_wait = $options['sleep_wait'] or 5;
        $this->web_host = $options['web_host'] or '127.0.0.1';
        $this->web_port = $options['web_port'] or 8080;    
    }
    
    private function _handshake($socket, $key) {
        $TAG = __FUNCTION__;
        $guid = 'b8573686-91e5-48fa-a467-985d809fad76';  
        if (!isset($socket)) {
            $this->_log->LogError('Socket was not set', $TAG);
            return false;            
        }        
        
        $this->_log->LogDebug('Receiving handshake headers...', $TAG);
        $buffer = '';
        if (socket_recv($socket, $buffer, 1024, MSG_WAITALL) === false) {
            $this->_log->LogError("Error recieving handshake headers", $TAG);
            return false;
        }
        
        $headers = Helpers::parse_headers($buffer);
        
        $this->_log->LogDebug('Received headers', $TAG);
        
        if (!isset($headers['Sec-Socket-Key']))
            throw new Exception('No secure socket key recieved');
            
        $hashstr = base64_encode(sha1($headers['Sec-Socket-Key'].$guid, true));
        
        $this->_log->LogDebug('Receiving handshake headers...', $TAG);
        
        $reply = sprintf(
            "X-ARDUINO/0.1 101 Socket Protocol Handshake\r\n".
            "Socket-Origin: %s\r\n".
            "Socket-Host: %s\r\n".
            "Sec-Socket-Accept: %s\r\n".
            "\r\n", $headers['Origin'], $headers['Host'], $hashstr);
        
        return socket_write($socket, $reply, strlen($reply));
    }
    
    private function _establish_arduino_connection() {        
        if (!($socket = socket_create_listen($this->arduino_port))) {
            $this->_log->LogFatal('Error creating listening arduino socket. '.socket_strerror(socket_last_error()), $TAG);
            return false;
        }
        
        socket_set_nonblock($socket);        
        
        $i = 0;
        do {            
            if ($i > 0)
                usleep($this->sleep_wait * 1000);
            $this->_log->LogDebug("Waiting for connection on port {$this->arduino_port}", $TAG);
            $i++;
        }while (!(@$ar_sock =& socket_accept($socket)));

        socket_close($socket);
        
        if (!$this->_handshake($ar_sock, $this->_auth_key = Helpers::generate_random_string(16))) {
            $this->_log->LogFatal('Error performing handshake with arduino server!', $TAG);
            close_socket($ar_sock);
            return false;
        }        
        
        return $ar_sock;
    }
    
    function close_sockets() {
        if ($this->_arduino_sock) 
            socket_close($this->_arduino_sock);
            
        $this->_arduino_sock = null;
            
        if ($this->_web_sock)
            $this->_web_sock->close();         
            
        $this->_web_sock = null;
    }
    
    private function _establish_websocket_connection() {
        $websocket = new WebSocket($this->web_host, $this->web_port);
        socket_set_block($websocket->establish_single());                
        return $websocket;
    }
    
    private function _start_routing() {
        $ar_sock =& $this->_arduino_sock;
        $web_sock =& $this->_web_sock->socket;        
        
        socket_set_block($web_sock);
        socket_set_block($ar_sock);
        
        while (true) {
            $read = array($ar_sock, $web_sock);
            socket_select($read, $write = null, $except = null, null);
            
            if ($read[1]) {
                $web_data = $this->_web_sock->read(1024);                                
                if (empty($web_data)) {
                    # Tell arduino server to restart connection
                    socket_write($ar_sock, '~CLOSE');
                    break;
                }
                
                $this->_log->LogDebug("WEB: {$web_data}");
                socket_write($ar_sock, $web_data);                
            } 
            
            if ($read[0]) {
                $ar_data = socket_read($ar_sock, 1024);                
                if (empty($ar_data)) break;
                
                $this->_log->LogDebug("AR: {$ar_data}");                
                $this->_web_sock->write(trim($ar_data));                             
            }            
        }
    }
    
    function _send_ready() {        
        socket_write($this->_arduino_sock, "~WEBREADY\n", 9);
    }
    
    function start() {
        $TAG = __FUNCTION__;
        
        $this->_log->LogInfo('Establishing connection with arduino host...', $TAG);
            
        if (!($this->_arduino_sock = $this->_establish_arduino_connection()))
            return false;
        
        socket_set_block($this->_arduino_sock);        
        
        $this->_log->LogInfo('Arduino connection established.', $TAG);
        $this->_log->LogInfo('Listening for web connections.', $TAG);
        
        if (!($this->_web_sock = $this->_establish_websocket_connection()))
            return false;
        
        $this->_log->LogInfo('Web connection established - notifying arduino router.', $TAG);
        $this->_send_ready();
        
        $this->_log->LogInfo('Starting routing', $TAG);
        $this->_start_routing();
        
        $this->close_sockets();  
        return true;      
    }
}

// signal handler function
function sig_handler($signo)
{
    global $router, $is_running;
    switch ($signo) {     
    case SIGINT:
    case SIGTERM:     
        if ($router)
            $router->close_sockets();    
        $is_running = false;
        exit;
    }
}

if (function_exists('pcntl_signal')) {
    // setup signal handlers
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGINT, "sig_handler");
}

function __main__() {        
    global $router_opts, $router, $is_running;
    
    $is_running = true;
    while ($is_running) {
        $router = new RoutingServer($router_opts);
    
        $log = new KLogger('php://stdout', LOGLEVEL, __FUNCTION__);
    
        $log->LogInfo('Starting routing server...');
    
        if (!$router->start()) {
            $log->LogError('Routing server failed to start');
            break;
        }   
    }
    
    $log->LogDebug('Server has quit!'); 
}


__main__();