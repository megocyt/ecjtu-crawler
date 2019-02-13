<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-02-13 11:16:49 
 * @Last Modified by: Megoc 
 * @Last Modified time: 2019-02-13 11:16:49 
 * Email: megoc@megoc.org 
 */
namespace Megoc\Ecjtu\Exceptions;

use \Exception;

class AccountIncorrectException extends Exception
{
    /**
     * Create an AccountIncorrectException
     *
     * @param string $message
     */
    public function __construct($message = '用户名或密码错误！')
    {
        parent::__construct($message, 401);
    }
}
