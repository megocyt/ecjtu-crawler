<?php
/*
 * @Author: Megoc
 * @Date: 2019-01-19 13:10:22
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-01-19 13:13:00
 * @Email: megoc@megoc.org
 * @Description: Create by vscode
 */
namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\PortalCASInterface;
use Symfony\Component\DomCrawler\Crawler;
use Megoc\Ecjtu\Traits\EducationTrait;

class PortalCAS implements PortalCASInterface
{
    use EducationTrait;
    /**
     * server base uri
     */
    const BASE_URI = 'http://cas.ecjtu.edu.cn/';

    /**
     * create an instance
     *
     * @param array $user
     */
    public function __construct(array $user = [])
    {
        $this->init_cache_handler('cas.ecjtu.edu.cn');

        $this->init_http_client_handler();

        if (!empty($user['username']) && !empty($user['password'])) {
            $this->login($user);
        }
    }
    /**
     * service cas link
     *
     * @param string $service_cas_uri
     * @return string
     */
    public function cas_authority_link(string $service_cas_uri = '')
    {
        if (!$service_cas_uri || !preg_match('/^http:\/\/|https:\/\/.*/is', $service_cas_uri)) {
            throw new \Exception("Servcie cas uri is incorrect!", -1);
        }

        $response = $this->auth_client->get('cas/login?service=' . urlencode($service_cas_uri));
        $html = $response->getBody()->getContents();

        if (preg_match('/用户登录.*初始密码为：身份证后6位/is', $html)) {
            throw new \Exception("Get cas authority link failed!", -1);
        }

        $crawler = new Crawler($html);

        return $crawler->filter('a')->attr('href');;
    }
    /**
     * login
     *
     * @param array $user
     * @return void
     */
    public function login(array $user = [])
    {
        if (empty($user['username']) || empty($user['password'])) {
            if (!$this->username || !$this->password) {
                throw new \Exception("Username or password is needed to login system!", -1);
            }
        } else {
            $this->set_user($user);
        }

        if ($this->cache_handler->has($this->uid())) {
            $this->init_http_client_handler($this->uid());
            return;
        }

        $response = $this->a_client->get('cas/login');
        $html = $response->getBody()->getContents();
        /**
         * get lt token
         */
        try {
            $crawler = new Crawler($html);
            $lt_token = $crawler->filter('input[name=lt]')->attr('value');
        } catch (\Exception $e) {
            throw new \Exception("get lt token failed!", -1);
        }
        /**
         * get encrypt password
         */
        $enc_password = $this->encrypted_password($this->password);
        $service_cas_url = $this->service_name2service_uri('portal');
        /**
         * send authority request
         */
        $response = $this->a_client->post('cas/login', [
            'form_params' => [
                'encodedService' => urlencode($service_cas_url),
                'service' => $service_cas_url,
                'serviceName' => 'null',
                'loginErrCnt' => '0',
                'username' => $this->username,
                'password' => $enc_password,
                'lt' => $lt_token,
            ]
        ]);

        $html = $response->getBody()->getContents();

        if (preg_match('/错误的用户名或密码/is', $html)) {
            throw new \Exception("Username or password is incorrected!", -1);
        }

        $cookies = $response->getHeader('Set-Cookie');
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);

        $this->cache_handler->set($this->uid(), $cookies_string, 1800);
        $this->init_http_client_handler($this->uid());
    }
    /**
     * get encrpted password
     *
     * @param string $password
     * @return string
     */
    public function encrypted_password($password = '')
    {
        if (!$password) {
            return;
        }

        $response = $this->a_client->post('cas/loginPasswdEnc', [
            'form_params' => [
                'pwd' => $password
            ]
        ]);

        $html = $response->getBody()->getContents();
        $jsonArr = json_decode($html, true);

        return empty($jsonArr['passwordEnc']) ? : $jsonArr['passwordEnc'];
    }
    /**
     * get service authority url by name
     *
     * @param string $service_name
     * @return string
     */
    public function service_name2service_uri($service_name = '')
    {
        if (!$service_name) {
            return;
        }

        $services = [
            'education' => 'http://jwxt.ecjtu.jx.cn/stuMag/Login_dcpLogin.action',
            'elective' => 'http://xkxt.ecjtu.jx.cn/login/login_dcpLogin.action',
            'library' => 'http://lib1.ecjtu.jx.cn/goldwsdl/login.aspx',
            'portal' => 'http://portal.ecjtu.edu.cn/dcp/index.jsp',
        ];

        return empty($services[$service_name]) ? : $services[$service_name];
    }

}
