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
     * @var array
     */
    protected $user;

    public function __construct($user = [])
    {
        $this->user = $user;
    }

    /**
     * Ecard
     *
     * @return \Megoc\Ecjtu\Components\Ecard
     */
    public function getEcard()
    {
        if (empty($this->user['username']) || empty($this->user['ecard_password'])) {
            throw new \Exception("Ecard manager system login information is needed!", 1);
        }

        $this->Ecard = new Ecard([
            'username' => $this->user['username'],
            'password' => $this->user['ecard_password'],
        ]);

        return $this->Ecard;
    }
    /**
     * Education 
     *
     * @return \Megoc\Ecjtu\Components\Education
     */
    public function getEducation()
    {
        if (empty($this->user['username']) || empty($this->user['jwxt_password'])) {
            throw new \Exception("Education manager system login information is needed!", 1);
        }

        $this->Education = new Education([
            'username' => $this->user['username'],
            'password' => $this->user['jwxt_password'],
        ]);

        return $this->Education;
    }
    /**
     * Elective
     *
     * @return \Megoc\Ecjtu\Components\Elective
     */
    public function getElective()
    {
        if (empty($this->user['username']) || empty($this->user['jwxt_password'])) {
            throw new \Exception("Education manager system login information is needed!", 1);
        }

        $this->Elective = new Elective([
            'username' => $this->user['username'],
            'password' => $this->user['jwxt_password'],
        ]);

        return $this->Elective;
    }
    /**
     * Portal
     *
     * @return \Megoc\Ecjtu\Components\Portal
     */
    public function getPortal()
    {
        if (empty($this->user['username']) || empty($this->user['password'])) {
            throw new \Exception("Portal system login information is needed!", 1);
        }

        $this->Portal = new Portal([
            'username' => $this->user['username'],
            'password' => $this->user['password'],
        ]);

        return $this->Portal;
    }
    /**
     * check education manager system account info
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    public static function check_education_password($username = '', $password = '')
    {
        return Education::ckeck_password($username, $password);
    }
    /**
     * check ecard manager system account info
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    public static function check_ecard_password($username = '', $password = '')
    {
        return Ecard::check_password($username, $password);
    }
    /**
     * magic method
     *
     * @param [type] $property_name
     * @return void
     */
    public function __get($property_name)
    {
        $method = 'get' . $property_name;
        return $this->$method();
    }
}
