<?php
namespace Megoc\Ecjtu;

use Megoc\Ecjtu\Components\Ecard;
use Megoc\Ecjtu\Components\Education;
use Megoc\Ecjtu\Components\Elective;
use Megoc\Ecjtu\Components\Login;

/**
 * Ecjtu Manager 
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
class EcjtuManager
{
    /**
     * user information
     *
     * @var [type]
     */
    protected $user;


    public function __construct($user = [])
    {
        $this->user = $user;
    }
    
    /**
     * 获取Ecard 对象
     *
     * @return Object Ecard
     */
    public function getEcard()
    {
        if (empty($this->user['username']) || empty($this->user['ecard_password'])) {
            return ;
        }
        
        $this->Ecard = new Ecard([
            'username' => $this->user['username'],
            'password' => $this->user['ecard_password'],
        ]);

        return $this->Ecard;
    }

    /**
     * 获取教务系统对象
     *
     * @return Object Education
     */
    public function getEducation()
    {
        if (empty($this->user['username']) || empty($this->user['jwxt_password'])) {
            return ;
        }

        $this->Education = new Education([
            'username' => $this->user['username'],
            'password' => $this->user['jwxt_password'],
        ]);

        return $this->Education;
    }

    /**
     * 获取选课系统对象
     *
     * @return Object Elective
     */
    public function getElective()
    {
        if (empty($this->user['username']) || empty($this->user['jwxt_password'])) {
            return ;
        }

        $this->Elective = new Elective([
            'username' => $this->user['username'],
            'password' => $this->user['jwxt_password'],
        ]);
        
        return $this->Elective;
    }

    public function __get($property_name)
    {
        $method = 'get' . $property_name;
        return $this->$method();
    }
}
