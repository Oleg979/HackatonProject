<?php
require_once "Constants.php";
$data = file_get_contents('php://input');
//$data = $_POST['request'];

$request = json_decode($data,ture);

$token = $request['token'];
$method = $request['method'];
$params = $request['params'];

switch ($method){
    case "auth":
        if($params['login'] == null or $params['password'] == null)
    break;

}


function testToken(){
    global $key;
    return md5($key . substr( time(), 0, -1));
}


?>


