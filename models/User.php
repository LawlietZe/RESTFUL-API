<?php
use Phalcon\Mvc\Model,
    Phalcon\Mvc\Model\Message,
    Phalcon\Mvc\Model\Validator\InclusionIn,
    Phalcon\Mvc\Model\Validator\Uniqueness;

class User extends Model
{
   public $user_uid;
   public $openid;
   public $unionid;
   public $user_name;
   public $user_sex;
   public $user_portrait;
   public $register_time;
   public $user_phone;
   public $user_email;
   public $user_points;
   public $user_birthday;
   public $password;
   public $id_card;
   public $check_info;
   public $passport;
   public function getSource()
   {
       return 'user_tbl';
   }
}