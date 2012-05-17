<?php

class RequestHandler { 
  public $action = null;
  public $handlerObj = null;

  function __construct($action, $handlerObj) {
    $this->action = $action;

    $this->handlerObj = $handlerObj;
  }

  protected function error($message) {
    return array('state' => 'error', 'message' => $message);
  }

  protected function response($result = null) {
    if (!$result) {
      return false;
    }

    return array('state' => 'OK', 'result' => $result);
  }

  private function set_headers($length = false) {
    header('Content-Type: application/json');
    if ($length !== false) {
      header('Content-Length: '.$length);
    }
  }

  public function respond($context = array()) {
    $response = null;
    try {
      if (empty($this->action)) {
        throw new Exception('No action specified');
      }

      if (!method_exists($this->handlerObj, $this->action)) {
        throw new Exception("No action named '{$this->action}'.");
      }

      $response = $this->response($this->handlerObj->{$this->action}($context));

      $raw_resp = json_encode($response);      
    
    } catch (Exception $ex) {
      $response = $this->error($ex->getMessage());
      $raw_resp = json_encode($response);
    }

    $this->set_headers(strlen($raw_resp));

    echo $raw_resp;
    flush();
    return $response;
  }
}