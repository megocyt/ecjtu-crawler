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
     * 历史借阅记录
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
            // 分页信息
            $page_items = $crawler->filter('table#Table_MyPaper1 tr a')->each(function (Crawler $node, $n) {
                return str_replace('?PageNo=', '', $node->attr('href'));
            });

            // 记录信息
            $items = $crawler->filter('#DataGrid1 tr')->each(function (Crawler $node, $n) {
                if ($n == 0) {
                    return null;
                }

                $item = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                    return trim($nodec->text());
                });

                return $item;
            });

            $items = array_values(array_filter($items));
            // key => value
            $items_assoc = [];

            foreach ($items as $item) {
                $items_assoc[] = [
                    'book_name' => $item[0],
                    'login_no' => $item[1],
                    'bar_code' => $item[2],
                    'deal_at' => $item[3],
                    'operation' => $item[4],
                ];
            }

            return [
                'current_page' => $page,
                'total_pages' => intval(array_pop($page_items)),
                'lists' => $items_assoc,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * 个人信息
     *
     * @return array
     */
    public function profile()
    {
        $response = $this->auth_client->get('gdweb/ReaderTable.aspx');

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $items = $crawler->filter('td.TableContent1')->each(function (Crawler $node, $n) {
            $string = $node->text();

            $string = preg_replace('/ +/is', '', $string);
            $string = preg_replace('/\s*/is', '', $string);
            $string = preg_replace('/\n*/is', '', $string);

            return $string;
        });

        return [
            'student_id' => $items[2],
            'name' => $items[4],
            'sex' => $items[5] == '女' ? 2 : 1,
            'unit' => $items[6],
            'user_type' => $items[7],
            'checks' => $items[8],
            'checks_date' => $items[9],
            'phone' => $items[12],
            'email' => $items[13],
            'qfk' => floatval($items[14]),
            'qpk' => floatval($items[15]),
            'current_brooy' => $items[16],
        ];
    }
    /**
     * 通过cas认证用户信息
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
     * 登录系统
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

    /**
     * 超期公告列表
     *
     * @param integer $page
     * @return array
     */
    public static function expired_list(int $page = 1)
    {
        $response = self::curl(self::BASE_URI . 'gdweb/ExpiredList.aspx?PageNo=' . $page ?? 1);

        $html = $response['Body'] ?? '';

        try {
            $crawler = new Crawler($html);
            // 分页信息
            $page_items = $crawler->filter('table#Table_MyPaper1 tr a')->each(function (Crawler $node, $n) {
                return str_replace('?PageNo=', '', $node->attr('href'));
            });

            // 记录信息
            $items = $crawler->filter('#DataGrid1 tr')->each(function (Crawler $node, $n) {
                if ($n == 0) {
                    return null;
                }

                $item = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                    return trim($nodec->text());
                });

                $item[] = $node->filter('td a')->attr('href');

                return $item;
            });

            $items = array_values(array_filter($items));

            // key => value
            $items_assoc = [];

            foreach ($items as $item) {
                $items_assoc[] = [
                    'name' => $item[1],
                    'number' => $item[0],
                    'unit' => $item[2],
                    'book' => $item[3],
                    'book_uri' => self::BASE_URI . 'gdweb/' . $item[6],
                    'borrowed_at' => $item[4],
                    'should_return_at' => $item[5],
                    'borrow_dates' => (int)ceil((time() - strtotime($item[4])) / 86400),
                    'expired_dates' => (int)ceil((time() - strtotime($item[5])) / 86400),
                ];
            }

            return [
                'current_page' => $page,
                'total_pages' => intval(array_pop($page_items)),
                'lists' => $items_assoc,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * 搜索
     *
     * @param string $keyword
     * @param integer $page
     * @param string $show_type
     * @return array
     */
    public static function search($keyword, $page = 1, $show_type = '表格方式')
    {
        $cookies = self::before_search([
            'keyword' => $keyword,
            'show_type' => $show_type,
        ]);

        // 请求结果页面
        // '列表方式' => '/gdweb/ScarchList.aspx' ，页面太乱了，而且有些标签似乎没闭合，暂时没办法处理。
        // '表格方式' => '/gdweb/TableList.aspx' ，显示内容多，就是没有摘要
        $response = self::curl('/gdweb/TableList.aspx', [
            'method' => 'get',
            'headers' => array(
                'Cookie' => $cookies,
                'Referer' => self::BASE_URI . '/gdweb/default.aspx',
            ),
        ]);

        $crawler = new Crawler($response['Body']);

        $page_items = $crawler->filter('#LblPage a')->each(function (Crawler $node, $n) {
            return str_replace('tableList.aspx?Page=', '', $node->attr('href'));
        });

        $items = $crawler->filter('#GridView1 tr')->each(function (Crawler $node, $n) {

            if ($n == 0) {
                return null;
            }

            $item = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                return trim($nodec->text());
            });

            $item[] = str_replace('BaseView.aspx?ID=', '', $node->filter('a')->attr('href'));

            return  $item;
        });

        $items = array_values(array_filter($items));

        $items_assoc = [];

        $keyword_md5 = md5($keyword);

        foreach ($items as $item) {
            // 获取书籍的详细信息，非常耗时，所以关了
            // $book = self::book_view($keyword_md5 . ',' . $item[6], $cookies);

            $items_assoc[] = [
                'book' => $item[0],
                'author' => $item[1],
                'press' => $item[2],
                'publish_at' => $item[3],
                'bar_code' => $item[4],
                'index' => $item[5],
                'view_id' => base64_encode($keyword_md5 . ',' . $item[6]),
                // 'overview' => $book['overview'] ?? null,
                // 'locations' => $book['locations'] ?? null,
            ];
        }

        return [
            'current' => $page,
            'last' => ($last = array_pop($page_items)),
            'next' => $page + 1 > $last ? null : $page + 1,
            'previous' => $page - 1 <= 0 ? null : $page - 1,
            'items' => $items_assoc,
        ];
    }
    /**
     * 书籍详细信息
     *
     * @param string $view_id
     * @param string $cookie
     * @return array
     */
    public static function book_view($view_id, $cookie = null)
    {
        $view_id = base64_decode($view_id);

        $view_ids = explode(',', $view_id);

        if (count($view_ids) != 2) {
            return [];
        }

        $response = self::curl('gdweb/BaseView.aspx?ID=' . $view_ids[1], [
            'method' => 'get',
            'headers' => array(
                'Cookie' => $cookie ?? self::before_search($view_ids[0]),
                'Referer' => self::BASE_URI . '/gdweb/ScarchList.aspx',
            ),
        ]);

        $crawler = new Crawler($response['Body']);

        $info_items = $crawler->filter('#Table2 tr td')->each(function (Crawler $node, $n) {

            return $node->text();
        });

        $book_info_assoc = [
            'book' => $info_items[1] ?? null,
            'author' => $info_items[3] ?? null,
            'press' => $info_items[5] ?? null,
            'overview' => $info_items[11] ?? null,
        ];

        $location_items = $crawler->filter('#DataGrid1 tr')->each(function (Crawler $node, $n) {
            if ($n == 0) {
                return null;
            }

            $item = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                return $nodec->text();
            });

            return $item;
        });

        $location_items = array_values(array_filter($location_items));

        $location_items_assoc = [];

        foreach ($location_items as $item) {
            $location_items_assoc[] = [
                'bar_code' => $item[1],
                'location' => $item[2],
                'status' => $item[3],
                'info' => $item[4],
                'remark' => $item[5],
                'campus' => $item[6],
            ];
        }

        $book_info_assoc['locations'] = $location_items_assoc;

        return $book_info_assoc;
    }
    /**
     * 搜索前操作
     * 返回搜索页面的cookie
     *
     * @param string|array $options
     * @return string
     */
    protected static function before_search($options)
    {
        $fs = new FilesystemCache('lib1.ecjtu.jx.cn.search');

        if (!is_array($options)) {
            if ($fs->has($options)) {
                return $fs->get($options);
            } else {
                throw new \Exception("请重新搜索", 1);
            }
        }

        $keyword = $options['keyword'];
        $show_type = $options['show_type'];

        if (!$keyword) {
            return [];
        }

        $key = md5($keyword);

        if ($fs->has($key)) {
            return $fs->get($key);
        }

        $payload = [
            'ScriptManager1' => 'UpdatePanel1|Button1',
            '__EVENTTARGET' => '',
            '__EVENTARGUMENT' => '',
            '__LASTFOCUS' => '',
            '__VIEWSTATE' => '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QkYgMXB4IHNvbGlkIiBvbmNsaWNrPSJTZXRWYWx1ZSgn5Y2V54mH5py6Jyk7Ij4g5Y2V54mH5py6PC9TUEFOPiZuYnNwOyZuYnNwOzxTUEFOIHN0eWxlPSJCT1JERVItQk9UVE9NOiAjNDg5MUJGIDFweCBzb2xpZDsgQkFDS0dST1VORC1DT0xPUjogI0Q4RUZGNTsgQ1VSU09SOiBoYW5kOyBCT1JERVItUklHSFQ6ICM0ODkxQkYgMXB4IHNvbGlkIiBvbmNsaWNrPSJTZXRWYWx1ZSgn5LiJ6YeN6ZeoJyk7Ij4g5LiJ6YeN6ZeoPC9TUEFOPiZuYnNwOyZuYnNwOzxTUEFOIHN0eWxlPSJCT1JERVItQk9UVE9NOiAjNDg5MUJGIDFweCBzb2xpZDsgQkFDS0dST1VORC1DT0xPUjogI0Q4RUZGNTsgQ1VSU09SOiBoYW5kOyBCT1JERVItUklHSFQ6ICM0ODkxQkYgMXB4IHNvbGlkIiBvbmNsaWNrPSJTZXRWYWx1ZSgn566h55CG5a2mJyk7Ij4g566h55CG5a2mPC9TUEFOPiZuYnNwOyZuYnNwOzxTUEFOIHN0eWxlPSJCT1JERVItQk9UVE9NOiAjNDg5MUJGIDFweCBzb2xpZDsgQkFDS0dST1VORC1DT0xPUjogI0Q4RUZGNTsgQ1VSU09SOiBoYW5kOyBCT1JERVItUklHSFQ6ICM0ODkxQkYgMXB4IHNvbGlkIiBvbmNsaWNrPSJTZXRWYWx1ZSgn6L+e6ZSB5ZWG5bqXJyk7Ij4g6L+e6ZSB5ZWG5bqXPC9TUEFOPiZuYnNwOyZuYnNwOzxTUEFOIHN0eWxlPSJCT1JERVItQk9UVE9NOiAjNDg5MUJGIDFweCBzb2xpZDsgQkFDS0dST1VORC1DT0xPUjogI0Q4RUZGNTsgQ1VSU09SOiBoYW5kOyBCT1JERVItUklHSFQ6ICM0ODkxQkYgMXB4IHNvbGlkIiBvbmNsaWNrPSJTZXRWYWx1ZSgn5paH5b+D6ZuV6b6ZJyk7Ij4g5paH5b+D6ZuV6b6ZPC9TUEFOPiZuYnNwOyZuYnNwOzxTUEFOIHN0eWxlPSJCT1JERVItQk9UVE9NOiAjNDg5MUJGIDFweCBzb2xpZDsgQkFDS0dST1VORC1DT0xPUjogI0Q4RUZGNTsgQ1VSU09S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',
            '__EVENTVALIDATION' => '/wEWXALU0riXBwLgnZ70BALSwtXkAgLSwsGJCgKjjKTsAgKd5I/lCgKSi6WLBgKTi6WLBgKQi6WLBgKRi6WLBgKWi6WLBgKUi6WLBgKFi6WLBgKKi6WLBgL918izBALn/a+ACwLn/a+ACwLn/a+ACwLn/a+ACwLn/a+ACwLt37y/CQLt37y/CQLNhoi5AgKg5I/lCgLTrdSNCQL4rPjBBwKnururBQLxyoK0AQLY4tPQAgKOpaXzCQKKpbXzCQK9pcHzCQK1pf3zCQK2pfXzCQKPpYHzCQKJpfXzCQKMpbXzCQKMpYHzCQKNpYHzCQKNpZnzCQKNpcHzCQKOpZXyCQKPpbHzCQKPpanzCQKIpcnzCQKJpdHzCQKKpYHzCQKKpanzCQK7pa3zCQK7pcHzCQK0pYnzCQK0pY3zCQK0pa3zCQK0paXzCQK0pfXzCQK0pcnzCQK0pc3zCQK2pd3zCQK2pZHyCQK3pbHzCQKwpaXzCQKwpZHyCQKxpbnzCQKxpf3zCQKjpa3zCQKjpc3zCQKjpcHzCQK9pbnzCQK+pYHzCQK+pa3zCQK/pbHzCQK/pbXzCQK/pd3zCQK/pcHzCQK/pZHyCQK4pZnzCQK4panzCQK5paXzCQKrpbHzCQKkpcHzCQKkpZXyCQLwsfzjBgLxl8uOAwLSy7SPBQL3jKLTDQKM54rGBgLe64HXAgLB36CtCwLgyKrzCALsh7ajDQLj6JzNAQKA0PL0C4s7LrrbrTMtmcZfG4u8gZf1ccOn',
            'TxtIndex' => $keyword,
            'DropDownList1' => '所有',
            'DropDownList2' => '馆藏书目库',
            'DropDownList4' => '前方一致',
            'DropDownList3' => '入藏日期',
            'DropLanguage' => '不限',
            'RadioButtonList1' => $show_type,
            'HiddenValue' => '',
            'hidtext' => '题名',
            'hidValue' => '馆藏书目库',
            'DrpHouse' => '所有馆',
            'Button1' => '开始检索',
        ];

        $response = self::curl('/gdweb/default.aspx');
        // 发送post请求，即搜索请求
        $response1 = self::curl(self::BASE_URI . '/gdweb/default.aspx', [
            'method' => 'post',
            'payload' => $payload,
            'allow_redirects' => false,
            'headers' => array(
                'Cookie' => $response['Cookie'],
                'Referer' => self::BASE_URI . '/gdweb/default.aspx',
            ),
        ]);

        $fs->set($key, $response['Cookie'], 600);

        return $response['Cookie'];
    }
}
