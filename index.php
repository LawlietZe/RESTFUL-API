<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: "POST" ');
header('Access-Control-Allow-Headers: X-Requested-With');

// 定义文件上传根目录
define('UPLOAD_PATH', substr( __DIR__, 0, strrpos(__DIR__,DIRECTORY_SEPARATOR)) .DIRECTORY_SEPARATOR."daiyanren_server_phalcon/images/");
error_reporting(E_ERROR | E_WARNING | E_PARSE);//报告运行时错误
// error_reporting(E_ERROR);//报告运行时错误
// error_reporting(E_ALL);//报告运行时错误
$startTime = microtime();//设定时间标记。用于统计时间
// $debug = new \Phalcon\Debug(); //这里另一种phalcon提供的debug工具
// $debug->listen();
/**
 * 定义一个基本的输出对象
 * @var 数组类型
 */
$responseObj = array(
  'REUQEST' => $_REQUEST,
  'status'  => 1, //状态码
  'msg'     => 'ok', //提醒的信息
  'data'    => [], //数据体
  );
  
define('__DEBUG__', true);//调试模式
// define('__DEBUG__', false);//线上模式

try {
  $di = new \Phalcon\DI\FactoryDefault();
  $di->set('db', function(){
      if (__DEBUG__) {
       $db_password            = '';
       $db_host                = '192.168.0.105';
        // $db_password            = 'nineteen';
        // $db_host                = '121.40.31.31';
      }
      else {
        $db_password = '';//需要远程服务器密码
        $db_host     = 'localhost';
      }
      return new Phalcon\Db\Adapter\Pdo\Mysql(Array(
          "host"        => $db_host,
          "username"    => "root",
          "password"    => $db_password,
          "dbname"      => "xyt_db",
          "charset"     => "utf8",
          // 'unix_socket' => '/tmp/mysql.sock'
      ));
  });

  $di->set('redis', function() {
    if (__DEBUG__) {
      $redis_host = '127.0.0.1';
      $redis_password = '';
    }
    else {
      $redis_host = 'localhost';
      $redis_password = '';
    }
    return new Phalcon\Cache\Backend\Redis(array(
          "host"     => $redis_host,
          "password" => $redis_password,
          'port'     => 6379,
      ));
  });

  $loader = new \Phalcon\Loader();
  $loader->registerDirs(array(
    __DIR__ . '/models/',
    __DIR__ . '/controllers/'
  ))->register();

  /**
   * 定义公共输出对象
   */
  $di->set('response', function(){
    $response = new Phalcon\Http\Response;
    return $response;
  });

  $di->set('UserController', function(){
    $UserController = new UserController();
    return $UserController;
  });

  $di->set('NewUserController', function(){
    $NewUserController = new NewUserController();
    return $NewUserController;
  });

  /**
   * 开启api应用
   * @var app
   */
  $app = new \Phalcon\Mvc\Micro($di);

  $app->post('/api/reg', function() use ($app, $responseObj) {
    $data = $app->NewUserController->reg($app, $responseObj);
    $app->response->setJsonContent($data);
    $app->response->send();
  });

  $app->post('/api/login', function() use ($app, $responseObj) {
    $data = $app->UserController->userLoginAction($app, $startTime, $responseObj);
    $app->response->setJsonContent($data);
    $app->response->send();
  });

  $app->post('/api/change_user_info', function() use ($app, $responseObj) {
    $data = $app->NewUserController->changeUserInfo($app, $responseObj);
    $app->response->setJsonContent($data);
    $app->response->send();
  });
  //发送验证码
  $app->post('/api/sendsms', function() use ($app, $responseObj) {
    $data = $app->NewUserController->sendSMS($app, $responseObj);
    $app->response->setJsonContent($data);
    $app->response->send();
  });
  $app->post('/api/modify', function() use ($app, $responseObj) {
    $data = $app->NewUserController->modify($app, $responseObj);
    $app->response->setJsonContent($data);
    $app->response->send();
  });

  //文件上传例子
  $app->post('/api/upload', function() {
    $request = new Phalcon\Http\Request();
    //检查是否有文件上传
    if ($request->hasFiles() == true) {
        foreach ($request->getUploadedFiles() as $file) {
          try {
            // 上传文件名
            echo "上传文件名：".$file->getName()."<br />";
            // echo "临时文件路径：".$file->getTmp()."<br />";
            // 打印临时文件路径时报错 没有该方法
            // 手册中也没有提供详细的说明  如果知道请留言！
            echo "文件大小：".$file->getSize()."<br />";
            echo "文件类型：".$file->getType()."<br />";
            echo "错误代码：".$file->getError()."<br />";
            echo "上传表控件名：".$file->getKey()."<br />";
            echo "文件后綴".$file->getExtension()."<br />";

            // 移动到指定目录

              $file->moveTo(UPLOAD_PATH.$file->getName());
            } catch (Exception $e) {
              echo $e->getMessage();
            }
        }
    }
  });
  $app->handle();
} catch (Exception $e) {
    $responseObj['status'] = 0;
    $responseObj['msg']    = $e->getMessage();
    echo json_encode($responseObj);
    die();
}
