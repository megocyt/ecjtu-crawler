<?php
namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\ElectiveInterface;
use Megoc\Ecjtu\Components\Login;
use GuzzleHttp\Client;

/**
 * Elective
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
class Elective implements ElectiveInterface
{
    protected $baseUrl = 'http://xkxt.ecjtu.jx.cn/';
    protected $username;
    protected $password;
    protected $clientHandler;

    /**
     * User
     *
     * @var [type]
     */
    protected $user;
    

    public function __construct(array $user)
    {
        $this->set_user($user);
        $this->clientHandler = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 5,
            'headers' => [
                'Cookie' => $this->login(),
            ],
        ]);
    }
    
    public function course(string $term = '')
    {
        return ;
    }
    
    public function profile()
    {
        $response = $this->clientHandler->get('index/index_getPersonalInfo.action');

        echo $response->getBody()->getContents();
        return ;
    }
    /**
     * login
     *
     * @return void
     */
    protected function login()
    {
        if (empty($this->username) || empty($this->password)) {
            return '';
        }
        $LoginHandler = new Login;
        $LoginHandler->username($this->username);
        $LoginHandler->password($this->password);

        $login = $LoginHandler->verifyCode($this->baseUrl . 'servlet/code.servlet')->form([
            'username'       => $this->username,
            'password'       => $this->password,
            'verifyCodeName' => 'code',
        ])->login($this->baseUrl . 'login/login_checkout.action');

        var_dump($login);
        return $login;
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
