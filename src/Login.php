<?php
namespace Megoc\Ecjtu;

use Symfony\Component\Cache\Simple\FilesystemCache;
use GuzzleHttp\Client;
use Megoc\Ecjtu\CodeOCR\EcjtuOCR;

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
     * cache handler
     *
     * @var [type]
     */
    protected $cacheHandler;


    public function __construct()
    {
        $this->cacheHandler = new FilesystemCache();
        // var_dump($this->cacheHandler);exit;
        // $this->cacheHandler->clear();exit;
        // $this->verifyCode('http://jwxt.ecjtu.jx.cn/servlet/code.servlet');

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

           if ($http_code == 200) {
                if (preg_match('/success/is', $html)) {
                    $this->session_id($this->session_id);
                    $this->is_logined(true);
                    return $this->session_id();
                }
                if (preg_match('/验证码错误/is', $html)) {
                    if ($looper > 3) {
                        return null;
                    }
                    $looper++;
                    $this->verifyCode($this->codeUrl)->login($submit_url);
                }
            }

            return null;
        } catch (\Exception $e) {
            var_dump($e->getResponse());
        }
    }
    /**
     * check login status
     *
     * @return boolean
     */
    public function is_logined(bool $set_status = false)
    {
        $login_key = $this->hash('logined:' . $this->username());

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
        if ($code_uri) {
            $this->codeUrl = $code_uri;
        }

        if ($this->is_logined()) {
            return $this;
        }

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
