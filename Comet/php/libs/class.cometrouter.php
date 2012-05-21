<?php
require_once('class.sharedmemory.php');

define('CHAN_SHM', ftok(__FILE__, "c"));
define('AR_SHM', ftok(__FILE__, "a"));
define('WEB_SHM', ftok(__FILE__, "w"));
define('LISTEN_TIMEOUT', 60/*secs*/);
                                                         
class CometRouter {
  private $object = null;

  function __construct($object) {
    $this->object = $object;
  }

  private function _wait_data($key) {
    $sm = new SharedMemory($key, 'c', 0644, 1024);
    if (!$sm->open()) {
      throw new Exception('Unable to open shared memory segment.');
    }

    $iterations = 1;    
    $obj = false;

    while (true) {            
      $sm->lock();
      $payload = trim($sm->read());      

      if (!empty($payload)) {         
        @$obj = unserialize($payload);          
        if (!empty($obj)) { 
          $sm->clear();
          $sm->unlock();
          break;
        }
      }
  
      $sm->unlock();

      usleep(min(10000 * $iterations, 1000000));      
      if ($iterations >= LISTEN_TIMEOUT + 4) {
        return 'TMOUT';
      }
      
      $iterations++;      
    }
   
    $sm->close();

    return $obj;
  }

  function get_web_data() {
    return $this->_wait_data(WEB_SHM);
  }

  function get_ar_data() {
    return $this->_wait_data(AR_SHM);
  }

  private function _put_data($key) {
    $sm = new SharedMemory($key, 'c', 0644, 1024);
    if (!$sm->open()) {
      throw new Exception('Unable to open shared memory segment.');
    }
  
    $sm->lock();
    @$obj = unserialize(trim($sm->read()));
    if ($obj === false) { $obj = array(); }

    $obj[] = $this->object;

    $size = $sm->set(serialize($obj));    
    $sm->unlock();

    $sm->close();

    return ($size > 0);
  }

  function put_ar_data() {
    $payload = json_encode($this->object);
    if (!$payload) {
      throw new Exception('Nothing to write or encoding error');
    }
  
    return $this->_put_data(AR_SHM, $payload) ? 'PASS' : 'FAIL';
  }

  function put_web_data() {
    $payload = json_encode($this->object);
    if (!$payload) {
      throw new Exception('Nothing to write or encoding error');
    }
  
    return $this->_put_data(WEB_SHM, $payload) ? 'PASS' : 'FAIL';
  }

  function get_channel() {
    $sm = new SharedMemory(CHAN_SHM, 'c', 0644, 1);
    $sm->lock();
    $chan = $sm->read() or 0;
    $sm->set(++$chan);
    $sm->unlock();
    return $chan;
  }
}
