<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-01-12 18:08:06 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-01-17 15:43:11
 * @E-mail: megoc@megoc.org 
 * @Description: Create by vscode 
 */
namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\LibraryInterface;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Cache\Simple\FilesystemCache;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class Library implements LibraryInterface
{
    /**
     * base uri
     */
    const BASE_URI = 'http://lib1.ecjtu.jx.cn/';
    /**
     * username
     *
     * @var string
     */
    protected $username = '';
    /**
     * password
     *
     * @var string
     */
    protected $password = '';
    /**
     * a client handler
     *
     * @var \GuzzleHttp\Client
     */
    protected $a_client;
    /**
     * dcp authority client handler
     *
     * @var \GuzzleHttp\Client
     */
    protected $auth_client;

    /**
     * construct
     *
     * @param array $user
     */
    public function __construct(array $user = [], $uid = '')
    {
        $this->set_user($user);
        $this->init_client_handler($uid);
    }
    /**
     * history brooy record
     *
     * @param integer $page
     * @return void
     */
    public function history($page = 1)
    {
        try {
            $response = $this->auth_client->get('gdweb/HisdoryList.aspx?PageNo=' . $page);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            /**
             * get page table area
             */
            $pageTable = $crawler->filter('table#Table_MyPaper1')->html();
            $crawler1 = new Crawler($pageTable);
            $last = $crawler1->filter('a')->last();
            $string = $last->attr('href');
            $pages = substr($string, strpos($string, '=') + 1);
            /**
             * get data table area
             */
            $dataTable = $crawler->filter('table#DataGrid1')->html();
            $crawler2 = new Crawler($dataTable);
            $data = [];
            $crawler2->filter('tr')->each(function (Crawler $node, $i) use (&$data) {
                if ($i == 0) {
                    return;
                }

                $node->filter('td')->each(function (Crawler $node1, $i1) use (&$data, $i) {
                    switch ($i1) {
                        case '0':
                            $key = 'book_name';
                            break;
                        case '1':
                            $key = 'login_no';
                            break;
                        case '2':
                            $key = 'bar_code';
                            break;
                        case '3':
                            $key = 'deal_at';
                            break;
                        case '4':
                            $key = 'operation';
                            break;

                        default:
                            $key = '';
                            break;
                    }

                    $data[$i][$key] = trim($node1->text());
                });
            });

            return [
                'current_page' => $page,
                'total_pages' => $pages,
                'lists' => $data,
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
    /**
     * profile
     *
     * @return array
     */
    public function profile()
    {
        $response = $this->auth_client->get('gdweb/ReaderTable.aspx');
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);
        $data = [];
        $map = ['No.', 'CardNo.', 'student_id', 'avatar', 'name', 'sex', 'unit', 'user_type', 'checks', 'checks_date', 'address', 'post_code', 'phone', 'email', 'qfk', 'qpk', 'brooy_count', 'null'];

        $crawler->filter('td.TableContent1')->each(function (Crawler $node, $i) use (&$data, $map) {

            $string = trim($node->text());
            $string = preg_replace('/ +/is', '', $string);
            $string = preg_replace('/\s*/is', '', $string);
            $string = preg_replace('/\n*/is', '', $string);
            $string = trim($string);
            $data[$map[$i]] = $string;
        });

        if (empty($data)) {
            return [];
        }

        $unset_map = ['No.', 'CardNo.', 'avatar', 'address', 'null'];

        foreach ($unset_map as $key => $value) {
            unset($data[$value]);
        }

        if (!empty($data['sex'])) {
            $data['sex'] = preg_match('/女/is', $data['sex']) ? 2 : 1;
        }

        return $data;
    }
    /**
     * through cas authority id 
     *
     * @param string $key
     * @param string $cas_link
     * @return void
     */
    public function cas_authority($key, $cas_link = '')
    {
        if (!$key) {
            throw new \Exception("uninque id is needed!", 1);
        }

        if (!$cas_link) {
            $this->init_client_handler($key);
            return;
        }

        $response = $this->a_client->get($cas_link);
        $cookies = $response->getHeader('Set-Cookie');
        $html = $response->getBody()->getContents();
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);

        $crawler = new Crawler($html);
        $fullname = $crawler->filter('input[name=fullname]')->attr('value');
        $password = $crawler->filter('input[name=password]')->attr('value');
        $logintype = $crawler->filter('input[name=logintype]')->attr('value');

        $response = $this->a_client->post('gdweb/CheckTick.aspx', [
            'form_params' => [
                'fullname' => $fullname,
                'password' => $password,
                'logintype' => $logintype,
                'url' => 'ReaderTable.aspx',
            ],
            'headers' => [
                'Cookie' => $cookies_string,
            ]
        ]);

        $html = $response->getBody()->getContents();

        if (!preg_match('/退出登录/is', $html)) {
            throw new \Exception("Library manager system login failed!", 1);
        }

        if (!$key) {
            return;
        }

        $cache_handler = new FilesystemCache('lib1.ecjtu.jx.cn');
        $cache_handler->set($key, $cookies_string, 1800);
        $this->init_client_handler($key);

        return $this;
    }
    /**
     * login system
     *
     * @return void
     */
    protected function login()
    {
        if (empty($this->username) || empty($this->password)) {
            throw new \Exception("Username and password is needed to login system", 400);
        }

        $cache_handler = new FilesystemCache('lib1.ecjtu.jx.cn');
        $key = md5(sha1($this->username . $this->password));

        if ($cache_handler->has($key)) {
            return;
        }

        $response = $this->a_client->get('gdweb/ReaderLogin.aspx');
        $html = $response->getBody()->getContents();
        $cookies = $response->getHeader('Set-Cookie');
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);

        $crawler = new Crawler($html);
        $formdata = [];
        $input1 = $crawler->filter('input')->each(function (Crawler $node, $i) use (&$formdata) {
            $formdata[trim($node->attr('name'))] = trim($node->attr('value'));
        });

        $formdata['ScriptManager1'] = 'UpdatePanel1|ImageButton1';
        $formdata['DropDownList1'] = '读者条码';
        $formdata['TextBox1'] = $this->username;
        $formdata['TextBox2'] = $this->password;
        $formdata['ImageButton1.x'] = rand(3, 30);
        $formdata['ImageButton1.y'] = rand(2, 26);

        try {
            $response = $this->a_client->post('gdweb/ReaderLogin.aspx', [
                'form_params' => $formdata,
                'headers' => [
                    'Cookie' => $cookies_string,
                ],
            ]);
            $html = $response->getBody()->getContents();

            if (preg_match('/密码错误！请重新输入！/is', $html)) {
                throw new \Exception("Username or Password is incorrected", 401);
            }

            $cache_handler->set($key, $cookies_string, 600);
        } catch (\Exception $e) {
            throw $e;
        }
    }
    /**
     * set user form
     *
     * @param array $user
     * @return void
     */
    protected function set_user(array $user)
    {
        if (empty($user) || empty($user['username']) || empty($user['password'])) {
            // throw new \Exception("Username or Password is needed", 1);
        }

        $this->username = $user['username'];
        $this->password = $user['password'];
        $this->init_client_handler();
        $this->login();
    }
    /**
     * init handler
     *
     * @return void
     */
    protected function init_client_handler($key = '')
    {
        $this->a_client = new Client([
            'base_uri' => self::BASE_URI,
            'timeout' => 5,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            ]
        ]);

        $cache_handler = new FilesystemCache('lib1.ecjtu.jx.cn');

        if (!$key) {
            if (!$this->username) {
                return;
            }

            $key = md5(sha1($this->username . $this->password));
        }

        if ($cache_handler->has($key)) {
            $this->auth_client = new Client([
                'base_uri' => self::BASE_URI,
                'timeout' => 5,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
                    'Cookie' => $cache_handler->get($key),
                ]
            ]);
        }
    }
}