<?php

class RequestHandler { 
  public $action = null;
  public $args = null;

  function __construct($action, $args) {
    $this->action = $action;
    $this->args = $args;
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

  public function respond() {
    try {
      if (empty($this->action)) {
        throw new Exception('No action specified');
      }

      if (!method_exists($this, $this->action)) {
        throw new Exception("No action named '{$this->action}'.");
      }

      $response = $this->response($this->{$this->action}($this->args));

      $raw_resp = json_encode($response);
      $this->set_headers(strlen($raw_resp));

      echo $raw_resp;

      return $response;
    } catch (Exception $ex) {
      return $this->error($ex->getMessage());
    }
  }
}