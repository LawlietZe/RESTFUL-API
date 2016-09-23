<?php
class NewUserController extends \Phalcon\Mvc\Controller
{
    //gyc注册部分
    const TOKEN_SALT  = "Fuck~!@#$%^&*()_+You"; // 加盐值

		public function login($app, $responseObj)
		{

		}

    /**
     * 检查用户名是哪种类型，进行用户注册，目前是通过手机号检查
     * @param  [type] $app       [description]
     * @param  [type] $info      [description]
     * @param  [type] $checkCate [description]
     * @return [type]            [description]
     */
    static function userRegChecker($app, $info, $checkCate)
    {
        $phql    = "SELECT count(1) as u_count from User where ".$checkCate." = '".$info."' limit 1";
        $u_count = $app->modelsManager->executeQuery($phql)->getFirst()->u_count;
        if ($u_count > 0 ) {
            // $result['msg']    = '用户已存在！';
            // $result['status'] = 0;
            // return $result;
            return 0;
        }
        else {
            return true;
        }
    }
    /**
     * 实例化一个redis对象
     * @return boolean [description]
     */
    static function initRedis()
    {
        $redis = new Redis(); // new redis对象
        $redis_host = '127.0.0.1';
        $redis->connect($redis_host, '6379'); // 引用redis对象的connect方法
        return $redis; //返回redis对象
    }

		/**
		 * 验证码有效性验证
		 * @param  [type]  $yzm    [description]
		 * @param  [type]  $mobile [description]
		 * @return boolean         [description]
		 */
		static function isAuthOK($yzm, $mobile)
		{
				// $redis     = self::initRedis();
				// $redis_yzm = $redis->get('yzm'.$mobile);
				// if ($yzm === $redis_yzm) {
				// 		return true;
				// }
				// return false;
        return true;
		}

    /**
     * 用户注册逻辑
     * @param  对象 $app            [description]
     * @param  [type] $mt          [description]
     * @param  [type] $responseObj [description]
     * @return [type]              [description]
     */
    public function reg($app, $responseObj)
    {
        $user_name  = $app->request->getPost('user_name');
        $user_phone = $app->request->getPost('user_phone');
        $password   = $app->request->getPost('password');
        $yzm        = $app->request->getPost('yzm');
        if ($user_phone == '' || $password == '' || $yzm == '') {
            $responseObj['status'] = 0;
            $responseObj['msg']    = '手机号、密码、验证码均不能为空';
            return $responseObj;
        }

        if(!(preg_match("/1[3456789]{1}\d{9}$/", $user_phone))){
            $responseObj['status'] = 0;
            $responseObj['msg']    = '请检查你的手机号';
            return $responseObj;
        }

        $passwordOK    = self::isPasswordOk($password);
        // $passwordOK = true;
        $authenOK      = self::isAuthOK($yzm, $user_phone);
        // $authenOK   = true;
        if ($passwordOK && $authenOK) {
            //跟据手机号先判断用户是否存在，如果存在。则不必再注册！
            $feedback  = self::userRegChecker($app, $user_phone, 'mobile');
            // $feedck = 1;
            if (!$feedback) {
                // TODO:::
                // !0 = ?
                $responseObj['status'] = 0;
                $responseObj['msg']    = '此用户已存在，不必重复注册！';
                return $responseObj;
            }
            $user                   = new User();
            $user->mobile           = $user_phone;
            $user->password         = md5($password);
            // $user->yzm              = $yzm;
            $user->username         = $user_name;
            //设置用户默认信息
            $intN                   = 1;
            // $dataT                  = date('y-m-d h:i:s',time());
            $dataT                  = "2008-08-03 14:52:10";
            $varC                   = 'a';
            $user->email            = $varC;
            $user->birthday         = $dataT;
            $user->sex              = $intN;
            $user->money            = $intN;
            $user->county_code      = $intN;
            $user->city_code        = $intN;
            $user->update_times     = $intN;
            $user->create_at        = $dataT;
            $user->avatar_index     = $intN;
            $user->create_ip        = $varC;
            $user->last_update_ip   = $varC;
            $user->deviceid         = $varC;
            $user->devicetype       = $intN;
            $user->latitude         = $intN;
            $user->longitude        = $intN;
            $user->comefromplatform = $intN;
            $user->comefromperson   = $intN;
            $user->comefromapp      = $intN;
            $user->PUID             = $varC;
            $user->PUPWD            = $varC;
            $user->modi_pwd         = $intN;
            $user->authroize_string = $varC;
            $user->tk               = $varC;
            $user->level            = $intN;
            // 存储用户信息
            $res                    = $user->save();
            // 判断是否插入成功
            if ($res) {
                $checkResult = self::userMobileCheckLogic($app, $user_phone, md5($password));
                $user_token  = self::makeNewToekn($user_phone, $password);
                $responseObj['status'] = 1;
                $responseObj['msg']    = '注册成功';
                $responseObj['data']   = [
                  'usrname' => strval($checkResult->username),//将数组及类之外的变量类型转换成字符串类型
                  'token'   => strval($user_token),
                  ];
            }
            else {
                $responseObj['status'] = 0;
                $responseObj['msg']    = $res;
            }
        }
        else {
            $responseObj['status'] = 0;
            $responseObj['msg']    = '密码不合法 或 验证码或密码无效';
        }
        return $responseObj;
    }

    /**
     * 生成Token == 将token写入到redis
     * @param  [type] $uid      [description]
     * @param  [type] $password [description]
     * @return [type]           [description]
     */
    public static function makeNewToekn($uid, $password)
    {
        //token应该与主机有关
        // $token = md5(self::TOKEN_SALT . $uid . $password . time());
        $token = md5(self::TOKEN_SALT . $uid . $password);
        return $token;
    }

    /**
     * 判断密码合法性
     * @param  字符串 $password   用户输入的密码
     * @return 布尔值 true, false 返回密码合法性
     */
    private function isPasswordOk($password)
    {
        //检测密码由（8~20）位的字母与数字组成
        if (preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)) {
            return ture;
        }
        else {
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
        $conditons   = 'mobile = :mobile: and password = :password:';
        $parameters  = [
            'mobile' => $usermobile,
            'password' => $password,
        ];
        $user = User::findFirst([
            $conditons,
            'bind' => $parameters,
        ]);
        if ($user) {
            return $user;
        }
        else {
            return false;
        }
    }
    // gyc修改信息部分
    /**
     * 修改信息
     * @param  [type] $app         [description]
     * @param  [type] $responseObj [description]
     * @return [type]              [description]
     */
    public function changeUserInfo($app, $responseObj)
    {
        // TODO:::
        // 逻辑漏洞(已修复)
        $user_phone          = $app->request->getPost('user_phone');
        $password            = $app->request->getPost('password');
        $info_id             = $app->request->getPost('info_id');
        $information         = $app->request->getPost('information');
        $responseObj['data'] = '';
        if ($info_id === 'password') {
          $responseObj['status'] = 0;
          $responseObj['msg']    = '请使用修改密码功能修改此信息';
          return $responseObj;
        }
        $sql   = "select * from User where mobile = '".$user_phone."' and password = '".$password."' limit 1";
        $rs    = $app->modelsManager->executequery($sql);
        $count = $rs->count();
        if ($count) {
          $phql = "UPDATE User set ".$info_id." = '".$information."' where mobile = '".$user_phone."'";
          $rs2  = $app->modelsManager->executeQuery($phql);
          if ($rs2) {
            $responseObj['status'] = 1;
            $responseObj['msg']    = '修改成功';
            $responseObj['data']   = [
              'user_phone' => $user_phone,
              'new_info'   => $information,
            ];
            return $responseObj;
          }
          else {
            $responseObj['status'] = 0;
            $responseObj['msg']    = '修改失败，请检查你要选择的修改信息';
            return $responseObj;
          }
        }
        else {
          $responseObj['status'] = 0;
          $responseObj['msg']    = '修改失败，请检查你输入的手机号和密码';
        }
        return $responseObj;
    }

}
