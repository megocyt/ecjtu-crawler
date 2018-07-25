<?php
namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\LoginInterface;
use Symfony\Component\Cache\Simple\FilesystemCache;
use GuzzleHttp\Client;
use Megoc\Ecjtu\CodeOCR\EcjtuOCR;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Login 
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/23
 */
class Login implements LoginInterface
{
    /**
     * Username
     *
     * @var [type]
     */
    protected $username;
    /**
     * Password
     *
     * @var [type]
     */
    protected $password;
    /**
     * verify Code
     *
     * @var [type]
     */
    protected $verifyCode;
    /**
     * session_id
     *
     * @var [type]
     */
    protected $session_id;
    /**
     * cache key
     *
     * @var [type]
     */
    protected $cache_key;
    /**
     * form data
     *
     * @var [type]
     */
    protected $form;
    /**
     * form action url
     *
     * @var [type]
     */
    protected $formAction;
    /**
     * code url
     *
     * @var [type]
     */
    protected $codeUrl;
    /**
     * identify user form
     *
     * @var [type]
     */
    protected $user_id_form;
    /**
     * cache handler
     *
     * @var [type]
     */
    protected $cacheHandler;


    public function __construct()
    {
        $this->cacheHandler = new FilesystemCache();
        // $this->cacheHandler->clear();exit;

        return ;
    }
    /**
     * session id
     *
     * @param string $session
     * @return void
     */
    public function session_id(string $session_id = '')
    {
        if (empty($this->cache_key)) {
            $this->cache_key = $this->hash();
        }
        if (empty($session_id)) {

            if ($this->session_id) {
                return $this->session_id;
            }

            if (!$this->cacheHandler->has($this->cache_key)) {
                return null;
            }
            return $this->cacheHandler->get($this->cache_key);
        } else {
            $this->cacheHandler->set($this->cache_key, $session_id, 3600);
        }
    }
    /**
     * Hash key
     *
     * @return boolean
     */
    protected function hash($key='')
    {
        $key = $key ? : $this->formAction . '| |' . $this->username();
        return md5(sha1($key));
    }
    /**
     * Set or get username
     *
     * @param string $username
     * @return void
     */
    public function username(string $username = '')
    {
        return empty($username) ? $this->username : ($this->username=$username);
    }
    /**
     * set or get password
     *
     * @param string $password
     * @return void
     */
    public function password(string $password = '')
    {
        return empty($password) ? $this->password : ($this->password=$password);
    }
    /**
     * set form data
     *
     * @param array $form
     * @return void
     */
    public function form(array $form = [])
    {
        if (empty($form)) {
            return $this->form;
        }

        $this->form = $form;
        $this->user_id_form = $form;

        return $this;
    }
    /**
     * set or get form action url
     *
     * @param string $submit_url
     * @return void
     */
    public function formAction(string $submit_url)
    {
        if (empty($formAction)) {
            return $this->formAction;
        }
        
        $this->formAction = $formAction;

        return $this;
    }
    /**
     * Undocumented function
     *
     * @param string $submit_url
     * @return void
     */
    public function login(string $submit_url = '')
    {
        static $looper = 1;

        if ($submit_url) {
            $this->formAction = $submit_url;
        }
        /**
         * check status
         */
        if ($this->is_logined()) {
            return $this->session_id();
        }

        if (!$this->formAction) {
            return '';
        }

        if (!$this->verifyCode && $this->codeUrl) {
            $this->verifyCode($this->codeUrl);
        }

        /**
         * construct request params
         */
        if (empty($this->form)) {
            $this->form = [
                'UserName' => $this->username,
                'Password' => $this->password,
            ];

            if (!empty($this->verifyCode)) {
                $this->form['code'] = $this->verifyCode;
            }
        } else {
            if (!empty($this->form['verifyCodeName'])) {
                $this->form[$this->form['verifyCodeName']] = $this->verifyCode;
                unset($this->form['verifyCodeName']);
            }
        }

        /**
         * construct request
         */
        $client = new Client([
            'headers' => [
                'Cookie' => $this->session_id(),
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            ],
        ]);

        try {
            $response = $client->request('POST', $this->formAction, [
                'form_params' => $this->form,
            ]);

            $http_code = $response->getStatusCode();
            $html      = $response->getBody()->getContents();
            $headers   = $response->getHeaders();

            if (empty($this->session_id)) {
                $this->session_id = join('', $response->getHeader('Set-Cookie'));
                $this->session_id = str_replace('Path=/; HttpOnly', '', $this->session_id);
            }

            /**
             * if http code equal 200
             * do request success action
             */
           if ($http_code == 200) {
                $crawler            = new Crawler($html);
                $ecard_error_tip    = '';

                try {
                    $ecard_error_tip    = $crawler->filter('p.biaotou')->text();
                } catch (\Exception $e) {
                    // var_dump($e);
                }

                /**
                 * decide whether login succeed
                 */
                if (preg_match('/验证码错误/is', $html)) {
                    if ($looper > 3) {
                        throw new \Exception("Verify code error, program have attempt " . ${looper}-1 . " times", 1);
                    }
                    $looper++;
                    $this->verifyCode($this->codeUrl)->login($submit_url);
                }

                if (preg_match('/用户名或密码错误/is', $html)) {
                    throw new \Exception("Username or password error", 1);
                }

                if (preg_match('/success/is', $html)) {
                    $this->session_id($this->session_id);
                    $this->is_logined(true);
                    return $this->session_id();
                }

                if (preg_match('/个人信息/is', $html)) {
                    var_dump(31314);
                    $this->session_id($this->session_id);
                    $this->is_logined(true);
                    return $this->session_id();
                }

                if (empty($ecard_error_tip)) {
                    $this->session_id($this->session_id);
                    $this->is_logined(true);
                    return $this->session_id();
                }
            }

            return null;
        } catch (\Exception $e) {
            // var_dump($e);
        }
    }
    /**
     * check or set login status
     *
     * @param boolean $set_status
     * @return boolean
     */
    public function is_logined(bool $set_status = false)
    {
        if (empty($this->username)) {
            $key = $this->formAction . ' || logined:' . join(' || ', $this->user_id_form);
        } else {
            $key = $this->formAction . ' || logined:' . $this->username();
        }

        $login_key = $this->hash($key);
        
        if ($set_status) {
            $this->cacheHandler->set($login_key, true, 3600);
        } else {
            if ($this->cacheHandler->get($login_key)) {
                return true;
            } else {
                return false;
            }
        }
    }
    /**
     * get and recognize verify code
     *
     * @param string $code_uri
     * @return void
     */
    public function verifyCode(string $code_uri = '')
    {
        $this->codeUrl = $code_uri ? : $this->codeUrl;

        if (empty($this->codeUrl)) {
            $this->verifyCode = '';
        }

        if (empty($this->username) && empty($this->form)) {
            return $this;
        }

        if ($this->is_logined()) {
            return $this;
        }

        /**
         * construct guzzle clinet handler
         */
        $client = new Client([
            'verify'  => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            ],
        ]);

        try {
            $response         = $client->get($code_uri);
            $headers          = $response->getHeaders();
            $image_stream     = $response->getBody()->getContents();
            $this->session_id = join('', $response->getHeader('Set-Cookie'));
            $this->session_id = str_replace('Path=/; HttpOnly', '', $this->session_id);
            $OCR = new EcjtuOCR($image_stream);
            $this->verifyCode = $OCR->result();
            return $this;
        } catch (\Exception $e) {
            return $this;
        }
    }
}
