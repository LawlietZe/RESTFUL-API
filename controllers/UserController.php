<?php
class UserController extends \Phalcon\Mvc\Controller
{
    const TOKEN_SALT  = "Fuck~!@#$%^&*()_+You"; // 加盐值
    /**
     * 生成随机数方法
     * @param  [type]  $len  [随机数长度]
     * @param  integer $cate [description]
     * @return [type]        [description]
     */
    public static function random($len, $cate=0)
    {
        //cate=0，是纯数字 1是数字加英文
        $srcstr = "0123456789";
        //$srcstr="ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";//不必太复杂了
        mt_srand();//配置乱数种子
        $strs = "";
        for($i=0; $i < $len; $i++)
        {
            $strs .= $srcstr[mt_rand(0,9)];//验证码的下标位数
        }
        return strtoupper($strs);
    }

    /**
     * 短信平台返回信息
     * @param  [type] $form_string [description]
     * @param  [type] $request_url [description]
     * @return [type] $data             [description]
     */
    public static function get($form_string, $request_url)
    {
        $ch   = curl_init();//初始化一个curl对象
        if($form_string == null){
            $url  = $request_url; //如果传入的form_srting是空的，将request_url赋值给变量url
        }else{
            $url  = $request_url . "?". $form_string;//如果传入的form_string非空，将它作为参数接在request_url中并赋值给变量url
        }

        curl_setopt($ch, CURLOPT_URL, $url);//设置请求的url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//不要求输出结果
        $data = curl_exec($ch);//运行curl，请求网页
        curl_close($ch);//关闭curl请求
        return $data;//返回请求得到的数据
    }

    /**
     * 读取用户主页相关信息方法
     * @param  对象类型 $app 注入调用的对象
     * @param  时间戳 $mt  用来计算运行时间的变量
     * @return array      返回值
     */
    public function userReadAction($app, $mt, $responseObj)
    {
        $user_uid   = $app->request->getPost('uid');
        $token   = $app->request->getPost('token');

        $phql_user  = "SELECT * FROM User WHERE user_uid = '".$user_uid."'";
        $phal_cart  = "SELECT count(goods_id) as goodsnum FROM Cart WHERE uid = '".$user_uid."'";
        $query_user = $app->modelsManager->createQuery($phql_user);
        $query_cart = $app->modelsManager->createQuery($phal_cart);
        $user       = $query_user->execute()->getFirst();
        $cart_num   = $query_cart->execute()->getFirst()->goodsnum;
        if ($user) {
            $responseObj['status']            = 1;
            $responseObj['msg']               = 'ok';
            $user_info = [];
            $user_info = [
                'user_uid'  => $user->user_uid,
                'user_name' => $user->user_name,
                'portrait'  => $user->user_portrait,
            ];
            //此处需要查询消息表
            $user_info['message'][] = [
                'message_uid'     => "",
                'message_content' => "",
                'time'            => "",
            ];
            //此处需要查询
            $responseObj['data']['user_info'] = $user_info;
            $responseObj['data']['index_info'] = [
                'cart_count'     => $cart_num,
                'be_confirm'     => '',
                'being_purchase' => '',
            ];
        }
        else{
            $responseObj['status']            = 0;
            $responseObj['msg']               = '请登陆！';
            $responseObj['data']['user_info'] = '请登陆！';
        }
        $responseObj['timeSpend'] = microtime() - $mt.'ms';
        return $responseObj;
    }

    /**
     * 忘记密码方法
     * @param  对象类型 $app 注入调用的对象
     * @param  $user_phone 手机号
     * @param  时间戳 $mt  用来计算运行时间的变量
     * @return array      返回值
     */
    public function passwordModi($app, $mt, $responseObj)
    {
        $request    = new Phalcon\Http\Request();
        $user_phone = $request->getPost('user_phone');
        //判断手机号是否合法
        $isMobile   = self::usernameIsMobile($user_phone);
        if ($isMobile) {
            $password   = $request->getPost('password');
            $yzm        = $request->getPost('yzm');
            $passwordOK = self::isPasswordOk($password);
            $authenOK   = self::isAuthOK($yzm, $user_phone);
            if ($passwordOK && $authenOK) {
                $user           = User::findFirst("user_phone = '".$user_phone."'");
                //TODO:: 此处考虑用 user_uid 查询？
                $user->password = md5($password);
                $res            = $user->save();
                if($res){
                    $responseObj['status'] = 1;
                    $responseObj['msg']    = '密码修改成功';
                } else {
                    $responseObj['status'] = 0;
                    $responseObj['msg']    = '密码修改失败';
                }
                $responseObj['data']      = $res;
            }else{
                $responseObj['status'] = 0;
                $responseObj['msg']    = '密码不合法 或 验证码或密码无效';
                $responseObj['data']   = "";
            }
        }else{
            $responseObj['status'] = 0;
            $responseObj['msg']    = '输入的手机号有错误，请重新输入';
            $responseObj['data']   = '';
        }
        $mmt                            = microtime() - $mt;
        $responseObj['timeSpend']       = (string)$mmt."ms";
        return $responseObj;
    }

    /**
     * 修改密码方法
     * @param  对象类型 $app 注入调用的对象
     * @param  $passwrod 原密码
     * @param  $newpassword1 新密码
     * @param  $newpassword2 新密码校验
     * @param  时间戳 $mt  用来计算运行时间的变量
     * @return array      返回值
     */
     public function passwordChange($app, $mt, $responseObj)
     {
         $password = md5($app->request->getPost('password'));

         $uid = $app->request->getPost('uid');
         $newpassword1 = md5($app->request->getPost('newpassword1'));
         $newpassword2 = md5($app->request->getPost('newpassword2'));
         //校验两个新密码是否一致
         if ($newpassword1 === $newpassword2) {
             $conditons   = 'user_uid = :user_uid: and password = :password:';
             $parameters  = [
                 'user_uid' => $uid,
                 'password' => $password,
             ];
             $user = User::findFirst([
                 $conditons,
                 'bind' => $parameters,
             ]);
             if($user){
                 //查到用户，下面修改密码
                 $phql = "UPDATE User set password = '".$newpassword1."' where user_uid = '".$uid."'";
                 $rows = $app->modelsManager->executeQuery($phql);
                 $success = "0";
                 if($rows->success() == ture){
                     $responseObj['status'] = 1;
                     $responseObj['msg']    = '修改密码成功';
                     $responseObj['data']   = null;

                 }else{
                     $responseObj['status'] = 0;
                     $responseObj['msg']    = '修改密码失败';
                     $responseObj['data']   = null;
                 }
             } else {
                 $responseObj['status'] = 0;
                 $responseObj['msg']    = '原密码错误';
                 $responseObj['data']   = null;
             }
         } else {
             $responseObj['status'] = 0;
             $responseObj['msg']    = '两次输入的密码不一致';
             $responseObj['data']   = null;
         }
         $mmt                            = microtime() - $mt;
         $responseObj['timeSpend']       = (string)$mmt."ms";
         return $responseObj;
     }


    /**
     * 删除用户方法
     * @param  对象类型 $app 注入调用的对象
     * @param  $user_name 用户名
     * @param  $password  密码
     * @param  时间戳 $mt  用来计算运行时间的变量
     * @return array      返回值
     */
    public function userDelAction($app, $mt, $responseObj)
    {
        $user_uid = $app->request->getPost('user_uid');
        $password = $app->request->getPost('password');
        $password = md5($password);
        $phql     = "DELETE FROM User WHERE user_uid = '".$user_uid."' and password = '".$password."' limit 1";
        $query    = $app->modelsManager->createQuery($phql);
        $rows     = $query->execute();
        if($rows->success()){
          $success = "1";
        }else{
          $success = "0";
        }
        $mmt                            = microtime() - $mt;
        //构建输出对象
        $responseObj['status']          = 1;
        $responseObj['msg']             = 'ok';
        $responseObj['data']['success'] = $success;
        $responseObj['timeSpend']       = (string)$mmt."ms";
        return $responseObj;
    }


    /**
     * 发送短信方法
     * @param  [type] $phone   [description]
     * @param  [type] $content [description]
     * @return [type] $result  [description]
     */
    static function sendSMS($phone, $content)
    {
        $smsapi      = "api.smsbao.com"; //短信网关
        $charset     = "utf8"; //文件编码
        $user        = "xiuyetang"; //短信平台帐号
        $pass        = md5("1q2w3e4r5t"); //短信平台密码
        $request_url = "http://{$smsapi}/sms";
        $form_string = "u={$user}&p={$pass}&m={$phone}&c=".urlencode($content);
        //TODO:: 短信发送成功后返回的内容
        $result      = self::get($form_string, $request_url);
        return $result;
    }

    /**
    *检查用户名是哪种类型，进行用户注册，目前是通过手机号检查
    */
    static function userRegChecker($app, $info, $checkCate)
    {
        $phql    = "SELECT count(1) as u_count from User where ".$checkCate." = '".$info."' limit 1";
        $u_count = $app->modelsManager->executeQuery($phql)->getFirst()->u_count;
        if($u_count > 0 ){
            $result['msg']    = '用户已存在！';
            $result['status'] = 0;
            return $result;
        }
        else {
            return true;
        }
    }

    static function initRedis() {
        $redis = new Redis(); // new redis对象
        $redis_host = '127.0.0.1';
        $redis->connect($redis_host, '6379'); // 引用redis对象的connect方法
        return $redis; //返回redis对象
    }

    public function getAuthCodeAction($app, $mt, $responseObj)
    {
        $statusStr = array(
            "0" => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP地址限制",
            "50" => "内容含有敏感词"
            );
        $params['user_phone'] = $app->request->getPost('user_phone');
        //确认此号在系统中不存在。
        //如果存在，则返回错误。
        //如果不存在，则发送验证码。5分钟内有效。
        $feedback = self::userRegChecker($app, $params['user_phone'], 'user_phone');
        $isMobile = self::usernameIsMobile($params['user_phone']);
        if (($feedback && $isMobile) === true) { //不存在
            $yzm         = self::random(4);//生成验证码
            $SMS_CONTENT = "来自秀野堂官网的短信验证码：【".$yzm."】。此验证码将在验证后失效。by：秀野堂主。";
            $resultSMS   = self::sendSMS($params['user_phone'], $SMS_CONTENT);
            if ($resultSMS != '0') {
                $responseObj['status'] = 0;
                $responseObj['msg']    = $statusStr[$resultSMS];
                $responseObj['data']   = null;
            }
            else {
                $responseObj['status'] = 1;
                $responseObj['msg']    = $statusStr[$resultSMS];;
                $responseObj['data']   = $resultSMS;
                $redis = self::initRedis();
                $redis->setex('yzm'.$params['user_phone'], 6000, $yzm);
            }
        }
        else if ($isMobile !== true) {
            $responseObj['status'] = 0;
            $responseObj['msg']    = '请输入正确的手机号';
            $responseObj['data']   = $resultSMS;
        } else {
            $responseObj['status'] = 0;
            $responseObj['msg']    = '验证码获取失败，请重新尝试';
            $responseObj['data']   = $resultSMS;
        }
        return $responseObj;
    }

    /**
     * 修改密码时获取验证码
     * @param  [type] $app         [description]
     * @param  [type] $mt          [description]
     * @return  [type] $responseObj [description]
     * @return [type] $responseObj  [description]
     */
    public function getModiCodeAction($app, $mt, $responseObj)
    {
        $statusStr = array(
            "0"  => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP地址限制",
            "50" => "内容含有敏感词"
            );
        $params['user_phone'] = $app->request->getPost('user_phone');
        $isMobile             = self::usernameIsMobile($params['user_phone']);
        if ($isMobile) {
            $feedback = self::userRegChecker($app, $params['user_phone'], 'user_phone');
            if ($feedback['status'] === 0) { //手机号已经存在
                $yzm         = self::random(4);//生成验证码
                $SMS_CONTENT = "来自秀野堂官网的短信验证码：【".$yzm."】。此验证码将在验证后失效。by：秀野堂主。";
                $resultSMS   = self::sendSMS($params['user_phone'], $SMS_CONTENT);
                if ($resultSMS != '0') {
                    $responseObj['status'] = 0;
                    $responseObj['msg']    = $statusStr[$resultSMS];
                    $responseObj['data']   = null;
                }
                else {
                    $responseObj['status'] = 1;
                    $responseObj['msg']    = $statusStr[$resultSMS];;
                    $responseObj['data']   = $resultSMS;
                    $redis = self::initRedis();
                    $redis->setex('yzm'.$params['user_phone'], 6000, $yzm);
                }
            }
            else{
                $responseObj['status'] = 0;
                $responseObj['msg']    = '手机号还未注册，请先注册';
                $responseObj['data']   = $resultSMS;
            }
        }else{
            $responseObj['status'] = 0;
            $responseObj['msg']    = '输入的手机号有错误，请重新输入';
            $responseObj['data']   = '';
        }
        $mmt                      = microtime() - $mt;
        $responseObj['timeSpend'] = (string)$mmt."ms";
        return $responseObj;
    }


    /**
     * 注册时获取验证码，!!未使用
     * @param  [type] $app
     * @param  [type] $params [用户验证参数，验证方式]
     * @return [type] $result or $feedback
     */
    static function getauthcode($app, $params)
    {
        $feedback = self::userRegChecker($app, $params['user_phone'], 'user_phone');
        if($feedback === true) {
            $yzm                 = self::random(6);//生成验证码
            $SMS_CONTENT         = "来自秀野堂官网的短信验证码：【".$yzm."】。此验证码将在验证后失效。by：秀野堂主。";
            $resultSMS           = self::sendSMS($params['user_phone'], $SMS_CONTENT);
            $result['resultSMS'] = $resultSMS;
            $result['status']    = 1;
            $result['msg']       = '发送成功！';
            $YZM_EXPIRE_TIME     = 60 * 15 ; //写入Redis ，有效时间是15分钟
            $app->redis->setex('yzm_'.$params['mobile'], $YZM_EXPIRE_TIME, $yzm);
            //phalcon 关于Redis的文档在此
            //https://api.phalconphp.com/class/Phalcon/Cache/Backend/Redis.html
            return $result;
        }
        else {
            return $feedback;
        }
    }

    /**
     * 验证码有效性验证
     * @param  [type]  $yzm    [description]
     * @param  [type]  $mobile [description]
     * @return boolean         [description]
     */
    static function isAuthOK($yzm, $mobile)
    {
        $redis     = self::initRedis();
        $redis_yzm = $redis->get('yzm'.$mobile);
        if ($yzm === $redis_yzm) {
            return true;
        }
        return false;
    }

    /**
     * 用户注册逻辑
     * @param  对象 $app         [description]
     * @param  [type] $mt          [description]
     * @param  [type] $responseObj [description]
     * @param  数组 $params      用户登陆时提交的信息
     * @return [type]              [description]
     */
    public function userRegistAction($app, $mt, $responseObj)
    {
        $user_phone           = $app->request->getPost('user_phone');
        $password             = $app->request->getPost('password');
        $yzm                  = $app->request->getPost('yzm');
        if ($user_phone == '' || $password == '' || $yzm == '') {
            $responseObj['status']    = 0;
            $responseObj['msg']       = '用户名、密码、验证码均不能为空';
            $mmt                      = microtime() - $mt;
            $responseObj['timeSpend'] = (string)$mmt."ms";
            return $responseObj;
        }
        $passwordOK           = self::isPasswordOk($password);
        // $passwordOK           = true;
        $authenOK             = self::isAuthOK($yzm, $user_phone);
        if ($passwordOK && $authenOK) {
            //先判断用户是否存在，如果存在。则不必再注册！
            $feedback = self::userRegChecker($app, $user_phone, 'user_phone');
            if (!$feedback) {
                $responseObj['status'] = 0;
                $responseObj['msg']    = '此用户已存在，不必重复注册！';
                $mmt                      = microtime() - $mt;
                $responseObj['timeSpend'] = (string)$mmt."ms";
                return $responseObj;
            }
            $user                = new User();
            $register_time       = date('y-m-d h:i:s',time());
            $user->user_phone    = $user_phone;
            $user->register_time = $register_time;
            $user->password      = md5($password);
            $res                 = $user->save();
            if($res){
                $checkResult = self::userMobileCheckLogic($app, $user_phone, md5($password));
                $user_token  = self::makeNewToekn($user_phone, $password);
                $responseObj['status'] = 1;
                $responseObj['msg']    = '注册成功';
                $responseObj['data'] = [
                  'uid'        => strval($checkResult->user_uid),
                  'usrname'    => strval($checkResult->user_name),
                  'usr_avatar' => strval($checkResult->user_portrait),
                  'follows'    => strval(""),
                  'score'      => strval($checkResult->user_points),
                  'is_buyer'   => strval($checkResult->check_info),
                  'token'      => strval($user_token),
                  ];
            } else {
                $responseObj['status'] = 0;
                $responseObj['msg']    = $res;
            }
            $responseObj['timeSpend'] = microtime() - $mt.'ms';
            return $responseObj;
        }else{
            $responseObj['status'] = 0;
            $responseObj['msg']    = '密码不合法 或 验证码或密码无效';
        }

        return $responseObj;
    }

    /**
     * 买手加盟
     * @param  对象 $app         [description]
     * @param  [type] $mt          [description]
     * @param  [type] $responseObj [description]
     * @return [type] array  [description]
     */
    public function buyerAddAction($app, $mt, $responseObj)
    {
        $uid    = $app->request->getPost('uid');
        $token  = $app->request->getPost('token');
        if ($uid == '' || $token == '') {
            $responseObj['status']    = 0;
            $responseObj['msg']       = 'uid和token不能为空';
            $mmt                      = microtime() - $mt;
            $responseObj['timeSpend'] = (string)$mmt."ms";
            return $responseObj;
        }
        $phql       = "SELECT check_info FROM User WHERE user_uid= '".$uid."'";
        $query      = $app->modelsManager->createQuery($phql);
        $checkinfo  = $query->execute()->getFirst();
        $check      = $checkinfo->check_info;
        $real_name  = $request->getPost('real_name');
        $passport   = $request->getPost('passport');
        $id_card    = $request->getPost('id_card');
        $user_phone = $request->getPost('user_phone');

        if($real_name == '' || $passport == '' || $id_card == '' || $user_phone == '') {
            $responseObj['status'] = 0;
            $responseObj['msg']    = '真实姓名、护照号、身份证号、手机均不能为空';
            return $responseObj;
        }
        //TODO:: 里面可能要填写的申请信息，$apply_info里的信息需要完善
        $apply_info = [];
        switch ($check) {
            case '0':
                $responseObj['status'] = 1;
                $responseObj['msg']    = 'ok';
                $user                  = new User();
                //此处需要进行输入合法性验证
                $apply_time            = time();
                $phql                  = "UPDATE User set real_name = '".$real_name."', passport='".$passport."', id_card='".$id_card."', user_phone='".$user_phone."' where user_uid=".$uid."";
                $ret                   = $app->modelsManager->executeQuery($phql);
                if($ret){
                    //买手申请后，修改申请状态为审核中
                    $phql_check_info = "UPDATE User set check_info= 1 where user_uid='".$uid."'";
                    $update_check    = $app->modelsManager->executeQuery($phql_check_info);
                    if ($update_check) {
                        //这里可能需要填写申请成功后返回的相关信息
                        // $apply_info = [
                        //     'check_info' => 1,
                        // ];
                        $responseObj['data'] = $apply_info;
                    }
                } else {
                    //可能需要填写申请失败后返回的相关信息
                    $responseObj['data'] = $apply_info;
                }
                break;
            case '1':
                $responseObj['status'] = 1;
                $responseObj['msg']    = 'ok';
                //告诉用户正在审核中
                $responseObj['data']   = $apply_info;
                break;
            case '2':
                $responseObj['status'] = 1;
                $responseObj['msg']    = 'ok';
                //告诉用户审核驳回
                $responseObj['data']   = $apply_info;
                break;
            case '3':
                $responseObj['status'] = 1;
                $responseObj['msg']    = 'ok';
                //告诉用户审核通过
                $responseObj['data']   = $apply_info;
                break;
            default:
                $responseObj['status'] = 0;
                $responseObj['msg']    = '出错了！';
                break;
        }

        $mmt                      = microtime() - $mt;
        //构建输出对象
        $responseObj['data']      = [];
        $responseObj['timeSpend'] = (string)$mmt."ms";
        return $responseObj;
    }

   /**
    * 买手加盟申请状态
    * @param  对象 $app         [description]
    * @param  [type] $mt          [description]
    * @param  [type] $responseObj [description]
    * @return [type]              [description]
    */
    public function buyerAddStatusAction($app, $mt, $responseObj)
    {
        $uid    = $app->request->getPost('uid');
        $token  = $app->request->getPost('token');
        if ($uid == '' || $token == '') {
            $responseObj['status']    = 0;
            $responseObj['msg']       = 'uid和token不能为空';
            $mmt                      = microtime() - $mt;
            $responseObj['timeSpend'] = (string)$mmt."ms";
            return $responseObj;
        }
        $phql        = "SELECT User.check_info, User.apply_time, (select BuyerAddLog.reason from BuyerAddLog where BuyerAddLog.uid = '".$uid."' order by log_time desc limit 1) FROM User, BuyerAddLog WHERE User.user_uid= '".$uid."'";
        $query       = $app->modelsManager->createQuery($phql);
        $checkstatus = $query->execute()->getFirst();
        $check       = $checkstatus->check_info;
        $reason       = $checkstatus->reason;
        $create_time = strval($checkstatus->apply_time);
        switch ($check) {
            case 0:
                $responseObj['status'] = 0;
                $responseObj['msg']    = '您还未申请';
                $checkinfo = [
                    'status'      => (string)(0),
                    'message'     => "请先申请",
                    'create_time' => $create_time,
                    'reason'      => "请先申请",
                ];
                $responseObj['data'] = $checkinfo;
                break;
            case 1:
                $responseObj['status'] = 1;
                $responseObj['msg']    = 'ok';
                $checkinfo = [
                    'status'      => $check,
                    'message'     => "审核中",
                    'create_time' => $create_time,
                    'reason'      => "正在极速审核中，请耐心等待",
                ];
                $responseObj['data'] = $checkinfo;
                break;
            case 2:
                $responseObj['status'] = 1;
                $responseObj['msg']    = 'ok';
                $checkinfo = [
                    'status'      => $check,
                    'message'     => "审核驳回",
                    'create_time' => $create_time,
                    'reason'      => $reason,
                ];
                $responseObj['data'] = $checkinfo;
                break;
            case 3:
                $responseObj['status'] = 1;
                $responseObj['msg']    = 'ok';
                $checkinfo = [
                    'status'      => $check,
                    'message'     => "审核通过",
                    'create_time' => $create_time,
                    'reason'      => "审核已通过",
                ];
                $responseObj['data'] = $checkinfo;
                break;
            default:
                $responseObj['status'] = 0;
                $responseObj['msg']    = '状态异常！';
                break;
        }

        $mmt                      = microtime() - $mt;
        //构建输出对象
        $responseObj['timeSpend'] = (string)$mmt."ms";
        return $responseObj;
    }

    /**
    *  生成Token == 将token写入到redis
    */
    public static function makeNewToekn($uid, $password)
    {
        //token应该与主机有关
        // $token = md5(self::TOKEN_SALT . $uid . $password . time());
        $token = md5(self::TOKEN_SALT . $uid . $password);
        return $token;
    }

    /**
     * 用户登陆逻辑
     * @param  对象 $app         [description]
     * @param  [type] $mt          [description]
     * @param  [type] $responseObj [description]
     * @param  数组 $params      用户登陆时提交的信息
     * @return [type]              [description]
     */
    public function userLoginAction($app, $mt, $responseObj)
    {
        $redis                  = self::initRedis(); //初始化redis对象
        $params                 = array();
        $params['username']     = $app->request->getPost('user_name');
        $params['password']     = $app->request->getPost('password');
        $params['password']     = md5($params['password']);
        if ($params['username'] == '' || $app->request->getPost('password') == '') {
            $responseObj['status']    = 0;
            $responseObj['msg']       = '用户名和密码不能为空';
            $mmt                      = microtime() - $mt;
            $responseObj['timeSpend'] = (string)$mmt."ms";
            return $responseObj;
        }
        //TODO::此处有逻辑漏洞，应该检查一下。
        //先去redis去取用户，如果有，则直接判断，如果没有，再去数据库里面匹配。
        $userObj = unserialize($redis->get('u'.$params['username']));
        if($userObj['token'] == '') {
            $user_token = self::makeNewToekn($params['username'], $params['password']);
            //进入数据库逻辑
            //判断 $username 第一个字符串是不是数字，如果是，则以手机号逻辑判断之。
            $isMobile = self::usernameIsMobile($params['username']);
            if ($isMobile) {
                $checkResult = self::userMobileCheckLogic($app, $params['username'], $params['password']);
            }
            else {
                $checkResult = self::usernameCheckLogic($app, $params['username'], $params['password']);
            }
            if($checkResult !== false) {
                $responseObj['status']     = 1;
                $responseObj['msg']        = '登陆成功！';
                $responseObj['data'] = [
                  'uid'        => strval($checkResult->user_uid),
                  'usrname'    => strval($checkResult->user_name),
                  'usr_avatar' => strval($checkResult->user_portrait),
                  'follows'    => strval("0"),//TODO::这里我关注的人的数量
                  'score'      => strval($checkResult->user_points),
                  'is_buyer'   => strval($checkResult->check_info),
                  'token'      => strval($user_token),
                  ];
                //把用户信息存入token
                $redis->set('u'.$params['username'], serialize($responseObj['data']));
                $redis->setex('uid'.$checkResult->user_uid, 1 * 24 * 60 * 60, serialize($responseObj['data']));
              }
              else{
                $responseObj['status']     = 0;
                $responseObj['msg']        = '没有该用户，或密码错误';
                $responseObj['data'] = '';
              }
        }
        else {
            //进入redis逻辑
            $userObj['usr_avatar'] = strval($userObj['usr_avatar']);
            $responseObj['data'] = $userObj;
            $redis->setex('uid'.$userObj['uid'], 1 * 24 * 60 * 60, serialize($userObj));
        }

        //否则，以用户名逻辑判断之。
      //构建输出对象
      $mmt                       = microtime() - $mt;
      $responseObj['timeSpend']  = (string)$mmt."ms";
      return $responseObj;
    }

    /**
     * 判断密码合法性
     * @param  字符串 $password   用户输入的密码
     * @return 布尔值 true, false 返回密码合法性
     */
    private function isPasswordOk($password)
    {
        //检测密码由（8~20）位的字母与数字组成
        if(preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)){
            return ture;
        }else{
            return false;
        }
    }


    /**
     * 微信登陆逻辑
     * @param  字符串 $openid   微信的openid
     * @param  字符串 $comefrom 来自哪个平台
     * @return 数组           返回用户的信息，参考api说明：login_wx
     */
    private function wxUserLogin($openid, $comefrom)
    {

    }

    /**
     * 判断用户名是不是手机号
     * @param  字符串 $username [description]
     * @return bool           返回真假布尔值
     */
    private function usernameIsMobile($username)
    {
      if(preg_match("/1[3456789]{1}\d{9}$/", $username)){
        return true;
      }else{
        return false;
      }
    }

    /**
     * 判断用户名是否存在，是否可以登陆
     * @param  [对象] $app        [description]
     * @param  字符串 $username [description]
     * @param  字符串 $password [description]
     * @return 数组           用户信息体。
     */
    private function usernameCheckLogic($app, $username, $password)
    {
      $sql = "select * from User where user_name = '".$username."' and password = '".$password."' limit 1";
      $rs  = $app->modelsManager->executequery($sql)->getFirst();
      if($rs){
        return $rs;
      }else{
        return false;
      }
    }

    /**
     *
     * 判断手机号是不是存在，是否可以登陆
     * @param  [对象] $app        [description]
     * @param  字符串 $usermobile [description]
     * @param  字符串 $password   [description]
     * @return 数组             用户信息体
     */
    private function userMobileCheckLogic($app, $usermobile, $password)
    {
        $sql = "select * from User where user_phone = '".$usermobile."' and password = '".$password."' limit 1";
        $rs  = $app->modelsManager->executequery($sql);


        $conditons   = 'user_phone = :user_phone: and password = :password:';
        $parameters  = [
            'user_phone' => $usermobile,
            'password' => $password,
        ];
        $user = User::findFirst([
            $conditons,
            'bind' => $parameters,
        ]);

        if($user){
            return $user;
        }else{
            return false;
        }
    }

    /**
     *买手中心
     *waring!  没有查询数据
     * @param  [对象] $app        [description]
     * @param  字符串 $usermobile [description]
     * @param  字符串 $password   [description]
     * @return 数组             用户信息体
     */
    public function buyerCenterAction($app, $username, $password)
    {
      $uid = $app->request->getPost('uid');
      $phql_time    = "select start_time,end_time from OutBoundCycle where uid = '".$uid."'";
      $phql_money = "select money from User where user_uid = '".$uid."'";
      $phql_fan = "select count(*) as count from Fans where fans_id = '".$uid."'";
      $phql_income = "select sum(plan_money) as plan_money,
                            sum(wuliu_money) as wuliu_money from Plan where buyer_id = '".$uid."' and status = 3";
      $phql_task1 = "select count(status) as to_be_confirmed from Plan where status = 0 and buyer_id = '".$uid."'";
      $phql_task2= "select count(status) as in_process from Plan where status = 1 and buyer_id = '".$uid."'";
      $phql_task3 = "select count(status) as finished from Plan where status = 3 and buyer_id = '".$uid."'";
      $phql_average_score = "select avg(judge_star) as average_score from Plan where status = 1";

      $rows_time          = $app->modelsManager->executeQuery($phql_time);
      $rows_fan           = $app->modelsManager->executeQuery($phql_fan);
      $rows_money         = $app->modelsManager->executeQuery($phql_money);
      $rows_profile       = $app->modelsManager->executeQuery($phql_income);
      $rows_task1         = $app->modelsManager->executeQuery($phql_task1);
      $rows_task2         = $app->modelsManager->executeQuery($phql_task2);
      $rows_task3         = $app->modelsManager->executeQuery($phql_task3);
      $rows_average_score = $app->modelsManager->executeQuery($phql_average_score);

      $i = 0;
          $time['start']            = $rows_time[0]['start_time'];
          $time['end']              = $rows_time[0]['end_time'];
          $fans[0]['num']           = $rows_fan[0]['count'];
          $fans[0]['award']         = 0;
          $recent_outbound_cycle[0] = $time;
      $i++;

      $responseObj                                             = array();
      $responseObj['status']                                   = 1;
      $responseObj['msg']                                      = 'ok';
      $responseObj['data']['profile']['remaining']             = $rows_money[0]['money'];
      $responseObj['data']['profile']['deal_base']             = $rows_profile[0]['plan_money'] - $rows_profile[0]['wuliu_money'];
      $responseObj['data']['profile']['all_earning']           = $rows_profile[0]['plan_money'] - $rows_profile[0]['wuliu_money'];
      $responseObj['data']['profile']['average_score']         = round($rows_average_score[0]['average_score']);
      $responseObj['data']['profile']['trade_num']             = $rows_task2[0]['in_process'] + $rows_task3[0]['finished'];
      $responseObj['data']['profile']['score']                 = "0";
      $responseObj['data']['profile']['recent_outbound_cycle'] = $recent_outbound_cycle[0];
      $responseObj['data']['profile']['two_demention_code']    = "userid+profile";
      $responseObj['data']['tasks']['to_be_confirmed']         = $rows_task1[0]['to_be_confirmed'];
      $responseObj['data']['tasks']['in_process']              = $rows_task2[0]['in_process'];
      $responseObj['data']['tasks']['finished']                = $rows_task3[0]['finished'];
      $responseObj['data']['fans']                             = $fans[0];
      $responseObj['data']['timeSpend']                        = microtime() - $mt.'ms';
      return $responseObj;

    }

    /*******************************************************************
     *                          客服相关 api
    /********************************************************************/
        /**
     * 显示与客服的聊天记录
     * @param  对象 $app         [description]
     * @param  [type] $mt          [description]
     * @param  [type] $responseObj [description]
     * @return [type]              [description]
     */
    public function  listContactServiceAction($app, $mt, $responseObj)
    {
        $user_uid    = $app->request->getPost('uid');
        $receiver_id = 0;//客服id 固定为0
        //生成与客服聊天记录
        $Msg         = new  MsgController();
        $responseObj = $Msg->readCustomerService($app, $user_uid, $receiver_id);
        return $responseObj;
    }

    /**
     *  删除与客服的单条聊天记录
     * @param  [type] $app         [description]
     * @param  [type] $mt          [description]
     * @param  [type] $responseObj [description]
     * @return [type]              [description]
     */
    public function  delContactServiceAction($app, $mt, $responseObj)
    {
        $msg_id      = $app->request->getPost('msg_id');
        $receiver_id = 0;//客服id 固定为0
        $Msg         = new  MsgController();
        $responseObj = $Msg->delCustomerService($app, $msg_id);
        return $responseObj;
    }

    /**
     *  与客服对话
     * @param  [type] $app         [description]
     * @param  [type] $mt          [description]
     * @param  [type] $responseObj [description]
     * @return [type]              [description]
     */
    public function  addContactServiceAction($app, $mt, $responseObj)
    {
        $user_uid    = $app->request->getPost('uid');
        $receiver_id = '0';//客服id 固定为0
        $text        = $app->request->getPost('text');
        $Msg         = new  MsgController();
        $responseObj = $Msg->addCustomerService($user_uid, $receiver_id, $text);
        return $responseObj;
    }
    /*******************************************************************
     *                          客服相关 api
    /********************************************************************/

}
