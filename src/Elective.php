<?php
namespace Megoc\Ecjtu;
use Megoc\Ecjtu\EducationInterface;

/**
 * Elective
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
class Elective implements ElectiveInterface
{
    protected $baseUrl = 'http://xkxt.ecjtu.jx.cn/';
    /**
     * User
     *
     * @var [type]
     */
    protected $user;
    

    public function __construct(array $user)
    {
        if ($this->set_user($user)) {
            # code...
        }
    }
    
    public function course(string $term = '')
    {
        return ;
    }
    
    public function profile()
    {
        return ;
    }
    
    /**
     * Set User Login information
     *
     * @param array $user
     * @return bool
     */
    protected function set_user(array $user)
    {
        if ( empty($user) || empty($user['username']) || empty($user['password']) ) {
            return false;
        }

        $this->username = $user['username'];
        $this->password = $user['password'];
        return false;
    }    
}
