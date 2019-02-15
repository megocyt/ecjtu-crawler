<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-02-13 11:16:39 
 * @Last Modified by: Megoc 
 * @Last Modified time: 2019-02-13 11:16:39 
 * Email: megoc@megoc.org 
 */
namespace Megoc\Ecjtu\Exceptions;

use Exception;

class CaptchaException extends Exception
{
    /**
     * Create a CaptchaException
     *
     * @param string $message
     */
    public function __construct($message = '验证码错误')
    {
        parent::__construct($message, 405);
    }
}