<?php

require_once(dirname(__FILE__).'/libs/class.requesthandler.php');
require_once(dirname(__FILE__).'/libs/class.sharedmemory.php');

define('AR_SHM', ftok(__FILE__, "a"));
define('WEB_SHM', ftok(__FILE__, "w"));
define('LISTEN_TIMEOUT', 60/*secs*/);
                                                         
class ArduinoRouter extends RequestHandler {
  private function _wait_data($key) {
    $sm = new SharedMemory($key, 'c');

    $sm->open();

    $payload = trim($sm->consume());

    $iterations = 1;    
    while (empty($payload)) {
      usleep(min(10000 * $iterations, 1000000));      
      if ($iterations >= LISTEN_TIMEOUT + 4) {
        return 'TMOUT';
      }

      $payload = trim($sm->consume());      
      $iterations++;      
    }
   
    $sm->close();

    return json_decode($payload);
  }

  function get_web_data() {
    return $this->_wait_data(WEB_SHM);
  }

  function get_ar_data() {
    return $this->_wait_data(AR_SHM);
  }

  private function _put_data($key, $payload) {
    $sm = new SharedMemory($key, 'c');
    $sm->open();
    $size = $sm->append($payload);
    $sm->close();

    return ($size > 0);
  }

  function put_ar_data($args) {
    $payload = json_encode($args);
    if (!$payload) {
      throw new Exception('Nothing to write or encoding error');
    }
  
    return $this->_put_data(AR_SHM, $payload) ? 'PASS' : 'FAIL';
  }

  function put_web_data($args) {
    $payload = json_encode($args);
    if (!$payload) {
      throw new Exception('Nothing to write or encoding error');
    }
  
    return $this->_put_data(WEB_SHM, $payload) ? 'PASS' : 'FAIL';
  }
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
$args = isset($_REQUEST['args']) ? json_decode($_REQUEST['args']) : false;

$handler = new ArduinoRouter($action, $args);
$handler->respond();

flush();
