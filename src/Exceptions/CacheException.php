<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-02-13 11:13:31 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-02-13 11:16:42
 * Email: megoc@megoc.org 
 */
namespace Megoc\Ecjtu\Exceptions;

use Exception;

class CacheException extends Exception
{
    /**
     * Create a CacheException
     *
     * @param string $message
     * @param integer $code
     */
    public function __construct($message = '', $code = 406)
    {
        parent::__construct($message, $code);
    }
}