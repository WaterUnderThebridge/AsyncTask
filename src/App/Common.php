<?php
error_reporting(0);
ini_set('display_errors', '0');


function getLogFile($file){
    return $file."/task_".date('Y-m-d').".log";
}

function send_sms($taskData){
    if(empty($taskData['phone'])) return ["status"=>"error","msg"=>"phone empty"];
    $url = "https://api.mysubmail.com/message/send.json";
    $dataPost=array(
       "appid"=>"41502",
       "signature"=>"89dc3395ad347cafdd388655f751ea5d"
    );
    $dataPost["to"]=$taskData['phone'];
    $dataPost["content"]="【小小运动馆】".$taskData['content'];
    file_put_contents("/bak/log/swoole_task/sms_".date('Y-m-d').".log", "时间: ".date('Y-m-d H:i:s')."  ".json_encode($dataPost)."\r\n", FILE_APPEND);
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, $dataPost);
    curl_setopt($con, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($con, CURLOPT_TIMEOUT,4);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($con, CURLOPT_SSL_VERIFYHOST, FALSE);
    $result = curl_exec($con);
    curl_close($con);
    return $result;
}

function xsend_sms($phone,$tpl,$vars){
    $url = "https://api.mysubmail.com/message/xsend.json";
    $data='{
    "appid":"41502",
    "signature":"89dc3395ad347cafdd388655f751ea5d"
    }';
    $dataPost = json_decode($data, true);
    $dataPost["to"]=$phone;
    $dataPost["project"]=$tpl;
    $dataPost["vars"]=$vars;
    //var_dump( $data);die();
    file_put_contents('/bak/log/swoole_task/xsms.log', "时间: ".date('Y-m-d H:i:s')."  ".json_encode($dataPost)."\r\n", FILE_APPEND);
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, $dataPost);
    curl_setopt($con, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($con, CURLOPT_TIMEOUT,4);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($con, CURLOPT_SSL_VERIFYHOST, FALSE);
    $result = curl_exec($con);
    curl_close($con);
    return $result;

}


function xsend_sms_batch($tpl,$multi){
    $url = "https://api.mysubmail.com/message/multixsend.json";
    $data='{
    "appid":"41502",
    "signature":"89dc3395ad347cafdd388655f751ea5d"
    }';
    $dataPost = json_decode($data, true);
    $dataPost["project"]=$tpl;
    $dataPost["multi"]=$multi;

    //echo json_encode($dataPost);die();
    file_put_contents('/bak/log/swoole_task/xsms_batch.log', "时间: ".date('Y-m-d H:i:s')."  ".json_encode($dataPost)."\r\n", FILE_APPEND);
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, $dataPost);
    curl_setopt($con, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($con, CURLOPT_TIMEOUT,4);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($con, CURLOPT_SSL_VERIFYHOST, FALSE);
    $result = curl_exec($con);
    curl_close($con);
    return $result;
}
?>
