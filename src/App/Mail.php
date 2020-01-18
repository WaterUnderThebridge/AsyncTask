<?php 
namespace AsyncTask\Swoole;

ini_set('date.timezone','Asia/Shanghai');
date_default_timezone_set("Asia/Shanghai");
use swoole_server;
// use AsyncTask\Swoole\Server;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'Common.php';
class Mail
{
    protected $serv;
    protected $host = '0.0.0.0';
    protected $port = 9502;
    // 进程名称
    protected $taskName = 'swooleMailer';
    // PID路径
    protected $pidPath = '/run/swooleMail.pid';
    // log
    protected $logPath= "/bak/log/swoole_task/";
    // 设置运行时参数
    protected $options = [
        'worker_num' => 4, //worker进程数,一般设置为CPU数的1-4倍  
        'daemonize' => true, //启用守护进程
        'log_file' => '/var/log/swoole.log', //指定swoole错误日志文件
        'log_level' => 0, //日志级别 范围是0-5，0-DEBUG，1-TRACE，2-INFO，3-NOTICE，4-WARNING，5-ERROR
        'dispatch_mode' => 1, //数据包分发策略,1-轮询模式
        'task_worker_num' => 4, //task进程的数量
        'task_ipc_mode' => 3, //使用消息队列通信，并设置为争抢模式
        'open_eof_split' => true, //打开EOF_SPLIT检测
        'package_eof' => "\r\n", //设置EOF
        //'heartbeat_idle_time' => 600, //一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
        //'heartbeat_check_interval' => 60, //启用心跳检测，每隔60s轮循一次
    ];
    // 邮件服务器配置
    protected $config = [
        'smtp_server' => 'smtp.163.com',
        'username' => 'example@163.com',
        'password' => '',// SMTP 密码/口令
        'secure' => 'ssl', //Enable TLS encryption, `ssl` also accepted
        'port' => 465, // tcp邮件服务器端口
    ];
    // 安全密钥
    protected $safeKey = 'MYgGnQE33ytd2jDFADS39DSEWsdD24sK';


    public function __construct($config, $options = [])
    { 
        // 构建Server对象，监听端口
        $this->serv = new swoole_server($this->host, $this->port);

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->serv->set($this->options);

        $this->config = $config;

        // 注册事件
        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('Connect', [$this, 'onConnect']);
        $this->serv->on('Receive', [$this, 'onReceive']);
        $this->serv->on('Task', [$this, 'onTask']);  
        $this->serv->on('Finish', [$this, 'onFinish']);
        $this->serv->on('Close', [$this, 'onClose']);

        // 启动服务
        //$this->serv->start();
    }

    protected function init()
    {
        //
    }

    public function start()
    {
        // Run worker
        $this->serv->start();
    }

    public function onStart($serv)
    {
        // 设置进程名
        cli_set_process_title($this->taskName);
        //记录进程id,脚本实现自动重启
        $pid = "{$serv->master_pid}\n{$serv->manager_pid}";
        file_put_contents($this->pidPath, $pid);
    }

    //监听连接进入事件
    public function onConnect($serv, $fd, $from_id)
    {
        $serv->send($fd, "Hello {$fd}!" );
    }

    // 监听数据接收事件
    public function onReceive(swoole_server $serv, $fd, $from_id, $data)
    {
        $res['result'] = 'failed';
        $key = $this->safeKey;

        $req = json_decode($data, true);
        $action = $req['action'];
        $token = $req['token'];
        $timestamp = $req['timestamp'];
        if (time() - $timestamp > 18000) {
            $res['code'] = '已超时';
            $serv->send($fd, json_encode($res));
            exit;
        }

        $token_get = md5($action.$timestamp.$key);
        if ($token != $token_get) {
            $res['msg'] = '非法提交';
            $serv->send($fd, json_encode($res));
            exit;
        }
        $res['result'] = 'success';
        $serv->send($fd, json_encode($res)); // 同步返回消息给客户端
        $serv->task($data);  // 执行异步任务

    }

    /**
    * @param $serv swoole_server swoole_server对象
    * @param $task_id int 任务id
    * @param $from_id int 投递任务的worker_id
    * @param $data string 投递的数据
    */
    public function onTask(swoole_server $serv, $task_id, $from_id, $data)
    {
        $res['result'] = 'failed';
        $req = json_decode($data, true);
        $action = $req['action'];
        $mayday = $req['taskData'];
        file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." onTask: [".$action."].\n",FILE_APPEND) ;
     //   file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." onTaskData: [".var_export($data,true)."].\n",FILE_APPEND); 
        switch ($action) {
            case 'sendMail': //发送单个邮件
                $this->sendMail($mayday);
                break;
            case 'sendMailQueue': // 批量队列发送邮件
                $this->sendMailQueue($mayday);
                break;
            case 'SMS': // 批量队列发送邮件
                $this->sendSMS($mayday);
                break;
            case 'multiSMS': // 批量队列发送邮件
                $this->sendMultiSMS($mayday);
                break;
           case 'sync_camp': // 批量队列发送邮件
                $this->sync_camp($mayday);
                break;
            default:
                break;
        }
    }

	// 邮件发送及队列
    private function sendMail($taskData)
    {
        file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." onSend: ".var_export($taskData,true)."\n",FILE_APPEND);
        if(empty($taskData)){
            $msg='{"errcode":114,"errmsg":"empty data"}';
            file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." error: ".$msg."\n",FILE_APPEND);
            return;
        }
        $mail = new PHPMailer(true);
        try {
            $config = $this->config;
            //$mail->SMTPDebug = 2;        // 启用Debug
            $mail->isSMTP();   // Set mailer to use SMTP
            $mail->Host = $config['smtp_server'];  // SMTP服务
            $mail->SMTPAuth = true;                  // Enable SMTP authentication
            $mail->Username = $config['username'];    // SMTP 用户名
            $mail->Password = $config['password'];     // SMTP 密码/口令
            $mail->SMTPSecure = $config['secure'];     // Enable TLS encryption, `ssl` also accepted
            $mail->Port = $config['port'];    // TCP 端口
            $mail->CharSet  = "UTF-8"; //字符集
            $mail->Encoding = "base64"; //编码方式
            $mail->setFrom($config['username'], '系统管理员'); //发件人地址，名称

            //Recipients
            $mail->addAddress($taskData['emailAddress'], '亲');     // 收件人地址和名称
            //$mail->addCC('AsyncTasknet@163.com'); // 抄送
            //Attachments
            if (isset($taskData['attach'])) {
                $mail->addAttachment($taskData['attach']);         // 添加附件
            }
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $taskData['subject'];
            $mail->Body    = $taskData['body'];
            //$mail->AltBody = '这是在不支持HTML邮件客户端的纯文本格式';
            $res=$mail->send();
            file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." SendSuccess: [".var_export($res,true)."]\n",FILE_APPEND);
            return true;
        } catch (\Exception $e) {
            $err='Message could not be sent. Mailer Error: '. $mail->ErrorInfo;
            file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." SendError: [".$err."]\n",FILE_APPEND);
            return false;
        }
    }

    private function sendMailQueue($taskData)
    {
        //file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . " init_value: " . var_export($taskData, true) . "\n", FILE_APPEND);
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $password = 'Tlgc';
        $redis->auth($password);
        if (count($taskData) == count($taskData, 1)) {
            //file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." redis_setvalue: ".var_export($taskData,true)."\n",FILE_APPEND);
            $v = (is_object($taskData) || is_array($taskData)) ? json_encode($taskData) : $taskData;
            $redis->rpush('mailerlist', $v);
        } else {
            foreach ($taskData as $v) {
                //file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." redis_setvalue: ".var_export($v,true)."\n",FILE_APPEND);
                $v = (is_object($v) || is_array($v)) ? json_encode($v) : $v;
                $redis->rpush('mailerlist', $v);
            }
        }
        $count=0;
        swoole_timer_tick(2000, function ($timer) use ($redis,&$count) { // 启用定时器，每1秒执行一次
            $value = $redis->lpop('mailerlist');
            //file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . " redis_getvalue: " . $value. "\n", FILE_APPEND);
            if ($value) {
                //echo '获取redis数据:' . $value;
                $value = is_string($value) ? json_decode($value, true) : $value;
                $start = microtime(true);
                $rs = $this->sendMail($value);
                $end = microtime(true);
                if ($rs) {
                    $msg = '发送成功！' . var_export($value, true) . ', 耗时:' . round($end - $start, 3) . '秒' . PHP_EOL;
                } else {
                    !isset($value['failed'])&&$value['failed']=0;
                    $value['failed']++;
                    if($value['failed'] < 4){
                        $redis->rpush("mailerlist", json_encode($value));
                        //失败不过3次，重新来
                    }else{
                        $redis->rpush("mailerlist_err", json_encode($value));
                        // 把发送失败的加入到失败队列中，人工处理
                    }
                    $msg = '发送失败！' . var_export($value, true) ;
                }
                $count++;
                file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . "[{$count}]: SendFeedback: [" . $msg . "]\n", FILE_APPEND);
            } else {
                swoole_timer_clear($timer); // 停止定时器
                file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . " EmailList出队完成\n", FILE_APPEND);
            }
        });
    }

	// 短信发送及队列
    private function sendSMS($taskData)
    {
        try{
            #file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." preSend: [".var_export($taskData,true)."]\n",FILE_APPEND);
            #return;
            $res=send_sms($taskData);
            file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." SendSuccess: [".var_export($res,true)."]\n",FILE_APPEND);
            if($res&&strpos($res,"success")!=false) return true;
            return false;
        } catch (\Exception $e) {
            $err='Message could not be sent. Mailer Error: '. $e->getMessage();
            file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." SendError: [".$err."]\n",FILE_APPEND);
            return false;
        }
    }

    //同步oasis campaign活动记录
    private function sync_camp($taskData)
    {
        try{
            if(array_key_exists('only_callback_url',$taskData)){
              if(array_key_exists('defer',$taskData)&&is_numeric($taskData['defer'])){
                  sleep($taskData['defer']);
              }
              $fd=doCurlGetRequest($taskData['only_callback_url']);
              file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." sync_camp_success: [".var_export($fd,true)."]\n",FILE_APPEND);
            }else{
              $res=sync_camp($taskData);
              if($res=='ok'){
                 $fd=doCurlGetRequest($taskData['callback_url']);
                 file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." sync_camp_success: [".var_export($fd,true)."]\n",FILE_APPEND);
              }else{
                 file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." sync_camp_fail: [".var_export($res,true)."]\n",FILE_APPEND);
              } 
            }
        } catch (\Exception $e) {
            $err='Message could not be sent. Mailer Error: '. $e->getMessage();
            file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." sync_camp_error: [".$err."]\n",FILE_APPEND);
        }
    }

    private function sendMultiSMS($taskData)
    {
        //file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . " init_value: " . var_export($taskData, true) . "\n", FILE_APPEND);
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $password = 'Tlgc';
        $redis->auth($password);
        if (count($taskData) == count($taskData, 1)) {
            //file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." redis_setvalue: ".var_export($taskData,true)."\n",FILE_APPEND);
            $v = (is_object($taskData) || is_array($taskData)) ? json_encode($taskData) : $taskData;
            $redis->rpush('mailerlist', $v);
        } else {
            foreach ($taskData as $v) {
                //file_put_contents(getLogFile($this->logPath),date('Y-m-d H:i:s')." redis_setvalue: ".var_export($v,true)."\n",FILE_APPEND);
                $v = (is_object($v) || is_array($v)) ? json_encode($v) : $v;
                $redis->rpush('mailerlist', $v);
            }
        }
        $count=0;
        swoole_timer_tick(500, function ($timer) use ($redis,&$count) { // 启用定时器，每1秒执行一次
            //echo '获取redis数据:';
            $value = $redis->lpop('mailerlist');
            //file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . " redis_getvalue: " . $value. "\n", FILE_APPEND);
            if ($value) {
                $value = is_string($value) ? json_decode($value, true) : $value;
                $start = microtime(true);
                $rs = $this->sendSMS($value);
                $end = microtime(true);
                if ($rs) {
                    $msg = '发送成功！' . var_export($value, true) . ', 耗时:' . round($end - $start, 3) . '秒' . PHP_EOL;
                } else {
                    !isset($value['failed'])&&$value['failed']=0;
                    $value['failed']++;
                    if($value['failed'] < 3){
                        $redis->rpush("mailerlist", json_encode($value));
                        //失败不过3次，重新来
                    }else{
                        $redis->rpush("mailerlist_err", json_encode($value));
                        // 把发送失败的加入到失败队列中，人工处理
                    }
                    $msg = '发送失败！' . var_export($value, true) ;
                }
                $count++;
                file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . "[{$count}]: SendFeedback: [" . $msg . "]\n", FILE_APPEND);

            } else {
                swoole_timer_clear($timer); // 停止定时器
                file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') . " SMSList出队完成\n", FILE_APPEND);
            }
        });
    }

    /**
    * @param $serv swoole_server swoole_server对象
    * @param $task_id int 任务id
    * @param $data string 任务返回的数据
    */
    public function onFinish(swoole_server $serv, $task_id, $data)
    {
        //
    }


    // 监听连接关闭事件
    public function onClose($serv, $fd, $from_id) {
        $msg= "Client {$fd} close connection\n";
        echo $msg;
        file_put_contents(getLogFile($this->logPath), date('Y-m-d H:i:s') .$msg, FILE_APPEND);
    }

    public function stop()
    {
        $this->serv->stop();
    }


    
}
