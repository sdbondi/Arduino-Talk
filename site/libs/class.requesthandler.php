<?php

class RequestHandler { 
  public $request = null;
  public $response = null;

  function __construct($request) {
    $this->request = $request;
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

  public function get_response() {
    if (isset($this->response)) {
      return $this->response;
    }
    
    $request =& $this->request;

    try {
      if (!$request || !isset($request->action)) {
        throw new Exception('Invalid or empty request');
      }

      if (!method_exists($this, $request->action)) {
        throw new Exception("No action named '{$request->action}'.");
      }

      return $this->response = $this->response($this->{$request->action}($request->args));
    } catch (Exception $ex) {
      return $this->error($ex->getMessage());
    }
  }
}