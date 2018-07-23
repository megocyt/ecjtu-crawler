<?php
namespace Megoc\Ecjtu;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Education 
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
class Education implements EducationInterface
{
    /**
     * base url
     *
     * @var string
     */
    protected $baseUrl = 'http://jwxt.ecjtu.jx.cn/';
    /**
     * username
     *
     * @var [type]
     */
    protected $username;
    /**
     * password
     *
     * @var [type]
     */
    protected $password;
    /**
     * Http handler
     *
     * @var [type]
     */
    protected $clientHandler;


    public function __construct(array $user)
    {
        if ($this->set_user($user)) {
            # code...
        }

        $this->clientHandler = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 5,
            'headers' => [
                'Cookie' => $this->login(),
            ],
        ]);

    }

    public function score(string $term='')
    {
        $response = $this->clientHandler->get('scoreQuery/stuScoreQue_getStuScore.action');

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $crawler->filter('.s_term li');

        $crawler->each(function (Crawler $node, $i)
        {
            var_dump($node->html());
        });

        return ;
    }

    public function credit(string $term='')
    {
        return ;
    }

    public function schedule(string $term='')
    {
        return ;
    }
    
    public function daily(string $date)
    {
        return ;
    }
    
    public function exam(string $term='')
    {
        return ;
    }
    
    public function bexam(string $term='')
    {
        return ;
    }
    
    public function empty_classroom(string $term='')
    {
        return ;
    }
    
    public function experiment(string $term='')
    {
        return ;
    }
    
    public function classmate(string $term='')
    {
        return ;
    }
    
    public function class_number(string $term='')
    {
        return ;
    }
    /**
     * login
     *
     * @return void
     */
    protected function login()
    {
        $LoginHandler = new \Megoc\Ecjtu\Login;
        $LoginHandler->username($this->username);
        $LoginHandler->password($this->password);

        $login = $LoginHandler->verifyCode($this->baseUrl . 'servlet/code.servlet')->login($this->baseUrl . 'stuMag/Login_login.action');

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
        $this->password = md5($user['password']);
        return false;
    }
}
