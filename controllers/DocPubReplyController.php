<?php
class DocPubReplyController extends \Phalcon\Mvc\Controller
{
  /**
   * 判断用户密码是否合法
   * @param  [type]  $password [密码]
   * @return boolean           [description]
   */
  private static function isPasswordOk($password)
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
   * 检查用户是否存在
   * @param  [type]  $username [description]
   * @param  [type]  $password [description]
   * @return boolean           [description]
   */
  private static function isUserExisit($app, $username, $password)
  {
    $password = md5($password);
    $phql     = "SELECT * FROM User WHERE username = '".$username."' and password = '".$password."'";
    $rs       = $app->modelsManager->executeQuery($phql);
    $count    = $rs->count();
    if ($count) {
      return true;
    }
    else {
      return false;
    }
  }

  /**
   * 文章发表功能  @why 2016/9/23
   * @param  [type] $app         [description]
   * @param  [type] $responseObj [description]
   * @return [type]              [description]
   */
  public function pubDoc($app, $responseObj)
  {
    $username   = $app->request->getPost('username');
    $password   = $app->request->getPost('password');
    $uid        = $app->request->getPost('uid');
    $cid        = $app->request->getPost('cid');
    $title      = $app->request->getPost('title');
    $keywords   = $app->request->getPost('keywords');
    $content    = $app->request->getPost('content');
    $pubip      = 1;
    $pubhostid  = '1';
    $passwordOk = self::isPasswordOk($password);
    $userCheck  = self::isUserExisit($app, $username, $password);
    if ($passwordOk && $userCheck) {
      if ($uid == '' || $title == '' || $content == '' || $keywords == '' || $cid == '') {
        $responseObj['status'] = 0;
        $responseObj['msg']    = '填入信息不能为空';
        return $responseObj;
      }
      $replys            = new Doc();
      $replys->uid       = (int)$uid;
      $replys->cid       = (int)$cid;
      $replys->title     = $title;
      $replys->keywords  = $keywords;
      $replys->content   = $content;
      $replys->pubip     = $pubip;
      $replys->pubhostid = $pubhostid;
      $res               = $replys->save();
      if ($res) {
        $responseObj['status'] = 1;
        $responseObj['msg']    = '文章发表成功';
      }
      else {
        $responseObj['status'] = 0;
        $responseObj['msg']    = '文章发表失败';
      }
        return $responseObj;//返回状态码
    }
    else {
      $responseObj['status'] = 0;
      $responseObj['msg']    = '用户名或密码不正确';
      return $responseObj;
    }
    return $responseObj;
  }

  /**
   * 文章回复功能 @why 2016/9/23
   * @param  [type] $app         [description]
   * @param  [type] $responseObj [description]
   * @return [type]              [description]
   */
  public function replyDoc($app, $responseObj)
  {
    $username   = $app->request->getPost('username');
    $password   = $app->request->getPost('password');
    $uid        = $app->request->getPost('uid');
    $pid        = $app->request->getPost('pid');
    $content    = $app->request->getPost('content');
    $pubip      = 1;
    $pubhostid  = '1';
    $passwordOk = self::isPasswordOk($password);
    $userCheck  = self::isUserExisit($app, $username, $password);
    if ($passwordOk && $userCheck) {
      if ($uid == '' || $content == '' || $pid =='') {
        $responseObj['status'] = 0;
        $responseObj['msg']    = '填入信息不能为空';
        return $responseObj;
      }
      $replys            = new Reply();
      $replys->uid       = (int)$uid;
      $replys->pid       = (int)$pid;
      $replys->content   = $content;
      $replys->pubip     = $pubip;
      $replys->pubhostid = $pubhostid;
      $res               = $replys->save();
      if ($res) {
        $responseObj['status'] = 1;
        $responseObj['msg']    = '文章回复成功';
      }
      else {
        $responseObj['status'] = 0;
        $responseObj['msg']    = '文章回复失败';
      }
    }
    else {
      $responseObj['status'] = 0;
      $responseObj['msg']    = '用户名密码不正确';
    }
    return $responseObj;
  }

}
 ?>
