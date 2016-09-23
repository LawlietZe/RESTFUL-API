<?php
class UserController extends \Phalcon\Mvc\Controller
{
  public function whyAction($app, $responseObj)
    {
      $uid = $app->request->getPost('uid');
      $uid = $app->request->getPost('title');
      $uid = $app->request->getPost('keywords');
      $uid = $app->request->getPost('content');
    }

}
 ?>
