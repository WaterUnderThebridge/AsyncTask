<?php
error_reporting(0);
ini_set('display_errors', '0');
ini_set('date.timezone','Asia/Shanghai');
date_default_timezone_set("Asia/Shanghai");

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

//oasis同步
function sync_camp($param,$baseUrl="https://bbk.800app.com/uploadfile/staticresource/238592/279833/champion.aspx"){
  try{
    $str = postCurl($baseUrl,$param);
    if($str&&stripos($str,"object")!=false){
       return "fail";
    }
    if($str&&stripos($str,'"ok"')!=false){
      return "ok";
    }
    return "fail";
  } catch (\Exception $e) {
    return "fail";
    file_put_contents("/bak/log/swoole_task/sync_camp_".date('Y-m-d').".log", "Time: ".date('Y-m-d H:i:s')."  ".var_export($e,true)."\r\n", FILE_APPEND);

  }
}

function postCurl($url, $data){
    $headers = array('Content-Type: application/x-www-form-urlencoded');
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_POSTFIELDS, urldecode(http_build_query($data))); // Post提交的数据包
    curl_setopt($curl, CURLOPT_TIMEOUT, 20); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $ret = curl_exec($curl);
    $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    //记录请求日志
    $arrLog = array(
        'type' => 'Post',
        'url' => $url,
        'http_status' => $http_status,
        'curl_error' => curl_error($curl),
        'result' => $ret
    );

    file_put_contents("/bak/log/swoole_task/sync_camp_".date('Y-m-d').".log", "Time: ".date('Y-m-d H:i:s')."  ".var_export($arrLog,true)."\r\n", FILE_APPEND);
    curl_close($curl);
    $ret=preg_replace('/,"sql":.*}/','}',$ret);
    return $ret;
}

function doCurlGetRequest($url, $data = array(), $timeout = 10) {
    if($url == "" || $timeout <= 0){
        return false;
    }
    if($data != array()) {
        $url = $url . '?' . http_build_query($data);
    }

    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($con, CURLOPT_TIMEOUT, (int)$timeout);

    return curl_exec($con);
}

?>
