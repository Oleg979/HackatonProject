<?php
require_once "Constants.php";
require_once "errors.php";
$data = file_get_contents('php://input');
//$data = $_POST['request'];

$request = json_decode($data,ture);

$method = $request['method'];
$params = $request['params'];

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

        $token = $connection->query("SELECT * FROM `tokens` WHERE `uId`='{$bd_result['id']}'")->fetch_assoc();

        if($token)
            exit(sendResponse(["token" => $token['token']]));

        $token = md5(time());
        $connection->query("INSERT INTO `tokens`(`uId`, `token`) VALUES ('{$bd_result['id']}','$token')");

        exit(sendResponse(["token" => $token['token']]));
    break;

    case "registration":

        if($params['login'] == null or $params['password'] == null)
            exit(json_encode($errors[2]));

        $pass =  $params['password'];
        $login = $params['login'];

        $bd_result = $connection->query("SELECT * FROM `users` WHERE `login`='$login'")->fetch_assoc();

        if($bd_result)
            exit(json_encode($errors[7]));

        $code = rand (111110,999999);
        $uStatus = [
            "status" => "registering",
            "dateEnd" => time() + 86400
        ];

        $uStatus = json_encode($uStatus);
        $pass = password_hash($pass, PASSWORD_DEFAULT);

        $connection->query("INSERT INTO `users`(`login`, `paswordHash`, `uStatus`, `code`) VALUES ('$login','$pass','$uStatus','$code')");
        mail($login, "Подтверждение почты", "Код: " . $code);
        exit(sendResponse());
    break;

    case "confirmEmail":
        if($params['code'] == null or $params['login'] == null)
            exit(json_encode($errors[2]));

        $code = $params['code'];
        $login = $params['login'];

        $bd_result = $connection->query("SELECT * FROM `users` WHERE `code`='$code' AND `login`='$login'")->fetch_assoc();

        if(!$bd_result)
            exit(json_encode($errors[8]));

        $uStatus = [
            "status" => "auth",
        ];

        $uStatus = json_encode($uStatus);
        $connection->query("UPDATE `users` SET `uStatus`='$uStatus',`code`='NULL' WHERE `login`='$login'");

        exit(sendResponse());
    break;

    case "getPlaces":
        if($params['lat'] == null or $params['lon'] == null or $params['token'] == null)
            exit(json_encode($errors[2]));

        $xMy = round($params['lat'] * 111000);
        $yMy = round($params['lon'] * 111000);
        $token = $params['token'];

        $db_places = $connection->query("SELECT * FROM `places` WHERE 1");
        $db_user = $connection->query("SELECT * FROM `users` WHERE `id`=(SELECT `uId` FROM `tokens` WHERE `token`='$token')")->fetch_assoc();

        $res = [];


        $userSettings = json_decode($db_user['settings'],true);

        while ($row = $db_places->fetch_assoc()){

            $xPlace = round($row['latitude'] * 111000);
            $yPlace = round($row['longitude'] * 111000);

            $xPow = pow($xMy - $xPlace, 2);
            $yPow = pow($yMy - $yPlace, 2);


            if(sqrt($xPow + $yPow) <= $userSettings['radius'])
                foreach($userSettings['categories'] as $key)
                    if($key == $row['type'])
                        array_push($res,$row);
        }

        exit(sendResponse($res));
    break;

    case "setSettings":
        if($params['radius'] == null or $params['categories'] == null or $params['token'] == null)
            exit(json_encode($errors[2]));

        $token = $params['token'];
        $categories = [];

        foreach ($params['categories'] as $key => $value)
            if($value)
                array_push($categories,$key);

        $res = json_encode([
            "radius" => $params['radius'],
            "categories" => $categories
        ],true);

        $db_user = $connection->query("UPDATE `users` SET `settings`='$res' WHERE `id`=(SELECT `uId` FROM `tokens` WHERE `token`='$token')");
        exit(sendResponse());
    break;

    case "getAllDiscountFromPlace":
        if($params['id'] == null)
            exit(json_encode($errors[2]));

        $pId = $params['id'];
        $place = $connection->query("SELECT * FROM `places` WHERE `id`='$pId'")->fetch_assoc();
        $allDicount = implode(",",json_decode($place['discounts'],true));

        $db_discount = $connection->query("SELECT * FROM `discounts` WHERE `id` IN ($allDicount)");
        $res = [];

        while ($row = $db_discount->fetch_assoc())
            array_push($res,$row);

        exit(sendResponse($res));
    break;

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


