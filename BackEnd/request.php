<?php
require_once "Constants.php";
require_once "errors.php";
//$data = file_get_contents('php://input');
$data = $_POST['request'];

$request = json_decode($data,ture);

$token = $request['token'];
$method = $request['method'];
$params = $request['params'];

if($token == null)
    exit(json_encode($errors[0]));

if($method == null)
    exit(json_encode($errors[1]));

switch ($method){
    case "auth":
        if($params['login'] == null or $params['password'] == null)
            exit(json_encode($errors[2]));

        $pass =  $params['password'];
        $login = $params['login'];

        $bd_result = $connection->query("SELECT * FROM `users` WHERE `login`='$login'")->fetch_assoc();

        $hash = $bd_result['paswordHash'];

        if (!password_verify($pass, $hash))
            exit(json_encode($errors[5]));

        $uStatus = json_decode($bd_result['uStatus'],true);

        if($uStatus['status'] != "auth")
            exit(json_encode($errors[6]));

        exit(sendResponse());
    break;

    case "registration":

        if($params['login'] == null or $params['password'] == null or $params['email'] == null )
            exit(json_encode($errors[2]));

        $pass =  $params['password'];
        $login = $params['login'];
        $email = $params['email'];

        $bd_result = $connection->query("SELECT * FROM `users` WHERE `login`='$login' OR `email`='$email'")->fetch_assoc();

        if($bd_result)
            exit(json_encode($errors[7]));

        $code = md5(time());
        $uStatus = [
            "status" => "registering",
            "dateEnd" => time() + 86400
        ];

        $uStatus = json_encode($uStatus);
        $pass = password_hash($pass, PASSWORD_DEFAULT);

        $connection->query("INSERT INTO `users`(`login`, `paswordHash`, `email`, `uStatus`, `code`) VALUES ('$login','$pass','$email','$uStatus','$code')");
        mail($email, "Подтверждение почты", "Код: " . $code);
        exit(sendResponse());
    break;

    case "confirmEmail":
        if($params['code'] == null)
            exit(json_encode($errors[2]));

        $code = $params['code'];

        $bd_result = $connection->query("SELECT * FROM `users` WHERE `code`='$code'")->fetch_assoc();

        if(!$bd_result)
            exit(json_encode($errors[8]));

        $uStatus = [
            "status" => "auth",
        ];

        $uStatus = json_encode($uStatus);
        $connection->query("UPDATE `users` SET `uStatus`='$uStatus',`code`='NULL' WHERE `id`='{$bd_result['id']}'");

        exit(sendResponse());
    break;

}


function testToken(){
    global $key;
    //return md5($key . substr( time(), 0, -1));
    return $key;
}

////////////////////////////////////////////////
//--------------- sendResponse ---------------//
////////////////////////////////////////////////
function sendResponse($content = ""){

    $response = [
        "status" => "ok",
        "response" => $content
    ];

    header("content-type: application/json");

    return json_encode($response);
}
////////////////////////////////////////////////


?>


