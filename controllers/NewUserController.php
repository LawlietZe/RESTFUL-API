<?php
class NewUserController extends \Phalcon\Mvc\Controller
{
		/**
		 * 判断用户名是不是手机号
		 * @param  字符串 $username [description]
		 * @return bool           返回真假布尔值
		 */
		private function usernameIsMobile($username)
		{
			if(preg_match("/1[3456789]{1}\d{9}$/", $username)){ //进行正则表达式匹配,1开头，第二位其中选，后面任意9位数字
				return true;
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
			//参数化查询
			$conditons = 'mobile = :mobile: and password = :password:';
			$parameters = [
					'mobile' => $usermobile,
					'password' => $password,
			];
			$user = User::findFirst([
					$conditons,
					'bind' => $parameters,  //绑定参数
			]);
			if($user){
					return $user;
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
			$sql = "select * from User where username = '".$username."' and password = '".$password."' limit 1";
			$user  = $app->modelsManager->executequery($sql)->getFirst();
			if($user){
				return $user;
			}else{
				return false;
			}
		}

		/**
		 * Redis初始化
		 * @return boolean [description]
		 */
		static function initRedis() {
				$redis = new Redis(); // new redis对象
				$redis_host = '127.0.0.1';
				$redis->connect($redis_host, '6379'); // 引用redis对象的connect方法
				return $redis; //返回redis对象
		}

	/**
	 * 用户登陆逻辑
	 * @param  对象 $app         [description]
	 * @param  [type] $responseObj [description]
	 * @param  数组 $params      用户登陆时提交的信息
	 */
	public function login($app, $responseObj)
	{
		$redis  = self::initRedis(); //初始化redis对象
		$params = array();                     //参数数组
		$params['username'] = $app->request->getPost('username');//获取用户名
		$params['password'] = $app->request->getPost('password'); //获取密码
		// $params['password']     = md5($params['password']);

		if ($params['username'] == ''||$app->request->getPost('password') == ''){
			  $responseObj['status'] = 0;//失败状态
			  $responseObj['msg'] = '用户名和密码不能为空';
			  return $responseObj;//返回状态码
		}
		$ifMobile = self::usernameIsMobile($params['username']);//判断是否是手机用户
		if ($ifMobile) {
			//如果是手机按照手机逻辑检测，返回查询结果
			$checkResult = self::userMobileCheckLogic($app, $params['username'], $params['password']);
		}
		else {
			 //非手机逻辑检测
				$checkResult = self::usernameCheckLogic($app, $params['username'], $params['password']);
		}
		if($checkResult){			//返回值状态改变
				$responseObj['status']     = 1;
				$responseObj['msg']        = $checkResult;
				$responseObj['data'] = [
					'uid'        => strval($checkResult->user_uid),	 //把变量转换成字符串类型
					'usrname'    => strval($checkResult->user_name),
					];
			}
			else{
				$responseObj['status']     = 0;
				$responseObj['msg']        = '没有该用户，或密码错误';
				$responseObj['data'] = '';
			}
      return $responseObj;
	}






	public function reg($app, $responseObj)
	{

	}
//------------------- start panjian sendSMS-----------------------------
	public function sendSMS($app, $responseObj)
	{
		$username = $app->request->getPost('username');
		$mobile   = $app->request->getPost('mobile');
		$isMobile = self::isMobile($mobile);
		if ($isMobile) {
			$attrArr = array(
	    		'username' => "'" . $username . "'",
	    		'mobile'   => "'" . $mobile . "'",
    		);
			$mark = self::collating($attrArr);
			if ($mark) {
				$yzm     = rand(100000, 999999);
				$content = "[xyt] yzm = " . $yzm;
				self::setSMS($mobile, $content);
				self::update($attrArr, "yzm", $yzm);
				$responseObj['msg'] = "send yzm";
			}
			else {
				$responseObj['msg'] = "select null";
			}
		}else {
			$responseObj['msg'] = "errror mobile type";
		}
		return $responseObj;

	}

	/**
     *
     * @param  字符串 $username [description]
     * @return bool           返回真假布尔值
     */
    private function isMobile($mobile)
    {
      if (preg_match("/1[3456789]{1}\d{9}$/", $mobile)) {
        return true;
      }
	  else {
        return false;
      }
    }

    private function collating($attrArr){
    	$phql = null;
    	foreach($attrArr as $key => $value){
    		$phql .= "'" . $key . "' = " . $value . " and ";
    	}
    	$phql = rtrim($phql, ' and ');
    	$user = User::findFirst($phql);
    	if ($user) {
    		$result = true;
    	}
		else {
    		$result = false;
    	}
    	return $result;
    }

    /**
     * 发送短信方法
     * @param  [type] $phone   [description]
     * @param  [type] $content [description]
     * @return [type] $result  [description]
     */
    private function setSMS($phone, $content)
    {
        $smsapi      = "api.smsbao.com"; //短信网关
        $charset     = "utf8"; //文件编码
        $user        = "xiuyetang"; //短信平台帐号
        $pass        = md5("1q2w3e4r5t"); //短信平台密码
        $request_url = "http://{$smsapi}/sms";
        $form_string = "u={$user}&p={$pass}&m={$phone}&c=".urlencode($content);
        //TODO:: 短信发送成功后返回的内容
        self::getSMS($form_string, $request_url);
    }

    /**
     * 短信平台返回信息
     * @param  [type] $form_string [description]
     * @param  [type] $request_url [description]
     * @return [type] $data             [description]
     */
    private function getSMS($form_string, $request_url)
    {
        $ch = curl_init();//初始化一个curl对象
        if ($form_string == null) {
            $url  = $request_url; //如果传入的form_srting是空的，将request_url赋值给变量url
        }else {
            $url  = $request_url . "?". $form_string;//如果传入的form_string非空，将它作为参数接在request_url中并赋值给变量url
        }

        curl_setopt($ch, CURLOPT_URL, $url);//设置请求的url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//不要求输出结果
       	curl_exec($ch);//运行curl，请求网页
        curl_close($ch);//关闭curl请求
    }

    private function saveYzm($username, $mobile, $yzm){
    	$phql      = "username = '" . $username . "' and mobile = '" . $mobile . "'";
    	$user      = User::findFirst($phql);
    	$user->yzm = $yzm;
    	$user->save();
    }

	private function update($attrArr, $key, $value){
		$phql = null;
		foreach($attrArr as $key => $value){
			$phql .= "'" . $key . "' = " . $value . " and ";
		}
		$phql       = rtrim($phql, ' and ');
		$user       = User::findFirst($phql);
		$user->$key = $value;
		$user->save();
	}


//------------------- end panjian sendSMS-----------------------------






//------------------- start panjian modify-----------------------------
    public function modify($app, $responseObj){
    	self::sendSMS($app, $responseObj);
    	$username    = $app->request->getPost('username');
		$mobile      = $app->request->getPost('mobile');
		$yzm         = $app->request->getPost('yzm');
		$password    = $app->request->getPost('password');
		$attrArr_1 = array(
			'username' => "'" . $username . "'",
			'mobile'   => "'" . $mobile . "'",
			'yzm'      => "'" . $yzm . "'",
		);
		$mark = self::collating($attrArr_1);
		if ( $mark ) {
			$attrArr_2 = array(
				'username' => "'" . $username . "'",
			);
			self::update($attrArr_2, "password", $password);
			$responseObj['msg'] = "修改成功";
		}
		else{
			$responseObj['msg'] = "信息不匹配";
		}
		return $responseObj;
    }






//------------------- end panjian modify-------------------------------
}
