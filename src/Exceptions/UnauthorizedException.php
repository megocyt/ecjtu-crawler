<?php
/*
 * @Author: Megoc 
 * @Date: 2019-02-13 10:56:00 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-02-13 10:57:17
 */
namespace Megoc\Ecjtu\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    /**
     * Create an UnauthorizedException
     *
     * @param string $message
     */
    public function __construct($message = '')
    {
        parent::__construct($message, 401);
    }
}