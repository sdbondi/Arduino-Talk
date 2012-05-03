<?php 

class SharedMemory {
  public $shmop_id = false;

  private $key;
  private $flags;
  private $mode;
  private $size;

  private $_semaphor;
  private $_lock_depth = 0;
  private $_length = 0;

  function __construct($key, $flags, $mode = 0644, $size = 1024) {
    $this->key = $key;
    $this->flags = $flags;
    $this->mode = $mode;
    $this->size = $size;

    // Semaphore
    $this->_semaphor = sem_get($key);
  }

  public function open() {
    if ($this->shmop_id !== false) {
      return true;
    }

    $this->shmop_id = shmop_open(
      $this->key,
      $this->flags,
      $this->mode,
      $this->size
    );

    return ($this->shmop_id === false);
  }

  public function close() {
    if (!$this->shmop_id) { return true; }

    return shmop_close($this->shmop_id);
  }

  private function lock() {
    if (!$this->shmop_id) { return false; }      

    $this->_lock_depth++;
    if ($this->_lock_depth > 1) { return true; }

    return sem_acquire($this->_semaphor);
  }

  private function unlock() {
    if (!$this->_semaphor) { return true; }

    $this->_lock_depth--;
    if ($this->_lock_depth > 0) { return true; }

    return sem_release($this->_semaphor);
  }

  public function delete() {
    if (!$this->shmop_id) { return true; }

    $this->lock();    
    $this->clear();
    
    shmop_delete($this->shmop_id);  

    $this->close();
    $this->unlock();
  }

  public function get_length() {
    return $this->_length;
  }

  public function read() {
    $this->lock();
    $data = shmop_read($this->shmop_id, 0, $this->_length);
    $this->unlock();

    return $data;
  }

  public function consume() {
    $this->lock();
    $data = shmop_read($this->shmop_id, 0, shmop_size($this->shmop_id));
    $this->clear();
    $this->unlock();

    return $data;
  }

  public function write($data, $offset = 0) {
    $this->lock();
    $size = shmop_write($this->shmop_id, $data, $offset);
    $this->_length = strpos($buf, "\0");
    $this->unlock();

    return $size;
  }

  public function append($data) {
    $this->lock();
    $buf = $this->read();  
    $size = $this->write($data, $this->_length);
    $this->unlock();
    return $size;
  }

  public function set($data) {
    $this->lock();    
    $this->clear(strlen($data));
    $size = $this->write($data, 0);
    $this->unlock();

    return $size;
  }

  public function clear($offset = 0) {
    $this->lock();
    
    $i = 0;
    while ($i < $this->size) {
      shmop_write($this->shmop_id, "\0", $i++);
    }       
    
    $this->_length = 0;

    $this->unlock();
  }

}