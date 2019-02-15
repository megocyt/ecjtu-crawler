<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-02-13 11:16:27 
 * @Last Modified by: Megoc 
 * @Last Modified time: 2019-02-13 11:16:27 
 * Email: megoc@megoc.org 
 */
namespace Megoc\Ecjtu\Exceptions;

use Exception;

class UnassessException extends Exception
{
    /**
     * Create an UnassessException 
     *
     * @param string $message
     */
    public function __construct($message = '未完成评教，无法查询成绩信息！')
    {
        parent::__construct($message, 400);
    }
}
 