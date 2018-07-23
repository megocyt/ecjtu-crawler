<?php
namespace Megoc\Ecjtu;

class Ecard implements EcardInterface
{
    protected $user;

    public function __construct(array $user)
    {
        if ($this->set_user($user)) {
            # code...
        }
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
