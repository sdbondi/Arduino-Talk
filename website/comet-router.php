<?php
require_once(dirname(__FILE__).'/libs/class.cometrouter.php');
require_once(dirname(__FILE__).'/libs/class.requesthandler.php');

$object = isset($_REQUEST['object']) ? json_decode($_REQUEST['object']) : false;
$handlerObj = new CometRouter($object);

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
$handler = new RequestHandler($action, $handlerObj);
$handler->respond();
