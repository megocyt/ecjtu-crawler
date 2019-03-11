<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-01-12 18:08:06 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-02-13 11:18:09
 * @E-mail: megoc@megoc.org 
 * @Description: Create by vscode 
 */
namespace Megoc\Ecjtu\Components;

use GuzzleHttp\Client;
use Megoc\Ecjtu\Traits\HelperTraits;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\RequestException;
use Megoc\Ecjtu\Exceptions\CacheException;
use Megoc\Ecjtu\Interfaces\LibraryInterface;
use Megoc\Ecjtu\Exceptions\UnauthorizedException;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Megoc\Ecjtu\Exceptions\AccountIncorrectException;

class Library implements LibraryInterface
{
    use HelperTraits;
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
     * construct
     *
     * @param array $user
     */
    public function __construct(array $user = [])
    {
        $this->init_cache_handler('lib1.ecjtu.jx.cn');
        $this->init_http_client_handler();

        if (!empty($user['username']) && !empty($user['password'])) {
            $this->login($user);
        }
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
     * @param string $uid
     * @param string $cas_link
     * @return void
     */
    public function cas_authority(string $uid, string $cas_link = '')
    {
        if (!$uid) {
            throw new CacheException("uninque id is needed!", 1);
        }

        if (!$cas_link) {
            return $this->init_http_client_handler($uid);
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

        $this->cache_handler->set($uid, $cookies_string, 1800);
        $this->init_http_client_handler($uid);

    }
    /**
     * login system
     *
     * @return void
     */
    public function login(array $user = [])
    {
        if (empty($user['username']) || empty($user['password'])) {
            if (!$this->username || !$this->password) {
                throw new UnauthorizedException("Username or password is needed to login system!");
            }
        } else {
            $this->set_user($user);
        }

        if ($this->cache_handler->has($this->uid())) {
            return $this->init_http_client_handler($this->uid());
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
                throw new AccountIncorrectException();
            }

            $this->cache_handler->set($this->uid(), $cookies_string, 600);
            $this->init_http_client_handler($this->uid());
        } catch (\Exception $e) {
            throw $e;
        }
    }
}