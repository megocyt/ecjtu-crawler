<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-01-12 11:56:19 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-01-17 15:42:05
 * @E-mail: megoc@megoc.org 
 * @Description: Create by vscode 
 */

namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\ProtalInterface;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Simple\FilesystemCache;

class Portal implements ProtalInterface
{
    /**
     * cas server base uri
     */
    const CAS_BASE_URI = 'http://cas.ecjtu.edu.cn/';
    /**
     * dcp server base uri
     */
    const DCP_BASE_URI = 'http://portal.ecjtu.edu.cn/';
    /**
     * unique id
     *
     * @var string
     */
    public $uid = '';
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
     * cache key
     *
     * @var string
     */
    protected $cache_key = '';
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
    protected $auth_dcp_client;

    /**
     * logger handler
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * construct function
     *
     * @param array $user
     */
    public function __construct(array $user)
    {
        $this->set_user($user);
        $this->cache_key = md5(sha1($this->username . $this->password));
        $this->uid = $this->cache_key;
        $this->init_client_handler();
    }

    /**
     * get notifications list
     *
     * @param integer $page
     * @param integer $page_size
     * @return array
     */
    public function notifications($page = 1, $page_size = 10)
    {
        $response = $this->auth_dcp_client->post('dcp/pim/pim.action', [
            'body' => '{"map":{"method":"getAllPimList","params":{"javaClass":"java.util.ArrayList","list":["","6d8f122161a74b6f85168ba27833fd87","","","",""]},"pm":{"javaClass":"com.neusoft.education.edp.client.PageManager","pageSize":' . $page_size . ',"pageNo":' . $page . ',"totalCount":-1,"order":null,"filters":{"javaClass":"com.neusoft.education.edp.client.QueryFilter","parameters":{"javaClass":"java.util.HashMap","map":{}}},"pageSumcols":null,"pageSums":null,"sumcols":null,"isNewSum":null,"sums":null,"resPojoName":""}},"javaClass":"java.util.HashMap"}',
            'headers' => [
                'clientType' => 'json',
                'render' => 'json',
            ]
        ]);

        $html = $response->getBody()->getContents();

        try {
            $jsonArr = json_decode($html, true);

            if (empty($jsonArr['list'])) {
                return [];
            }

            $list = [];

            foreach ($jsonArr['list'] as $value) {
                $v = $value['map'];

                $list[] = [
                    'create_at' => date('Y-m-d H:i:s', substr($v['CREATE_TIME']['time'], 0, -3)),
                    'create_user' => $v['USER_NAME'],
                    'is_top' => $v['IS_TOP'],
                    'resource_id' => $v['RESOURCE_ID'],
                    'belong_unit' => $v['BELONG_UNIT_NAME'],
                    'type_name' => $v['TYPE_NAME'],
                    'is_read' => $v['IS_READ'],
                    'title' => $v['PIM_TITLE'],
                    'content' => $v['PIM_CONTENT'],
                    'views' => $v['VIEW_COUNT'],
                ];
            }

            $pager = $jsonArr['pagemanager'];

            return [
                'total_list' => $pager['totalCount'],
                'current_page' => $pager['pageNo'],
                'total_page' => ceil($pager['totalCount'] / $page_size),
                'lists_size' => count($list),
                'lists' => $list,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * get notification detail
     *
     * @param string $resource_id
     * @return array
     */
    public function notification_detail($resource_id = '')
    {
        if (!$resource_id) {
            return [];
        }

        $response = $this->auth_dcp_client->post('dcp/pim/pim.action', [
            'json' => [
                'javaClass' => 'java.util.HashMap',
                'map' => [
                    'method' => 'getPimInfoById',
                    'params' => [
                        'javaClass' => 'java.util.ArrayList',
                        'list' => [
                            $resource_id
                        ]
                    ]
                ]
            ],
            'headers' => [
                'render' => 'json',
                'clientType' => 'json',
            ]

        ]);

        $html = $response->getBody()->getContents();

        try {
            $jsonArr = \json_decode($html, true);

            if (empty($jsonArr)) {
                return [];
            }

            $data = $jsonArr['map'];
            $info = [
                'resource_id' => $data['RESOURCE_ID'],
                'user_id' => $data['USER_ID'],
                'user_name' => $data['USER_NAME'],
                'belong_unit_name' => $data['BELONG_UNIT_NAME'],
                'user_unit_name' => $data['USER_UNIT_NAME'],
                'is_top' => $data['IS_TOP'],
                'is_ess' => $data['IS_ESS'],
                'is_draft' => $data['IS_DRAFT'],
                'user_unit_id' => $data['USER_UNIT_ID'],
                'status' => $data['PIM_STATUS'],
                'type' => $data['TYPE_NAME'],
                'title' => $data['PIM_TITLE'],
                'overview' => $data['PIM_CONTENT'],
                'content_url' => $data['CONTENT_URL'],
                'views' => $data['VIEW_COUNT'],
                'create_at' => date('Y-m-d H:i:s', substr($data['CREATE_TIME']['time'], 0, -3)),
            ];

            $url = 'dcp/uploadfiles/json/' . $info['content_url'] . '?callback=jsonp_' . $info['resource_id'];
            $content_jsonp = $this->auth_dcp_client->get($url)->getBody()->getContents();
            $content_json = \substr($content_jsonp, 39, -1);
            $content_arr = json_decode($content_json, true);
            $info['content'] = $content_arr['result'];

            return $info;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * lost notifications 
     *
     * @param integer $page
     * @param integer $page_size
     * @return array
     */
    public function lost_notifications($page = 1, $page_size = 10)
    {
        $response = $this->auth_dcp_client->post('dcp/lost/lost.action', [
            'body' => '{"map":{"method":"getPublicLostDataShow","params":{"javaClass":"java.util.ArrayList","list":["","0"]},"pm":{"javaClass":"com.neusoft.education.edp.client.PageManager","pageSize":' . $page_size . ',"pageNo":' . $page . ',"totalCount":-1,"order":null,"filters":{"javaClass":"com.neusoft.education.edp.client.QueryFilter","parameters":{"javaClass":"java.util.HashMap","map":{}}},"pageSumcols":null,"pageSums":null,"sumcols":null,"isNewSum":null,"sums":null,"resPojoName":""}},"javaClass":"java.util.HashMap"}',
            'headers' => [
                'render' => 'json',
                'clientType' => 'json',
            ]
        ]);

        $html = $response->getBody()->getContents();

        try {
            $jsonArr = json_decode($html, true);

            if (empty($jsonArr['list'])) {
                return [];
            }

            $list = [];
            foreach ($jsonArr['list'] as $value) {
                $v = $value['map'];
                $list[] = [
                    'owner_name' => $v['LOSTINFOUSERNAME'],
                    'owner_user_id' => $v['LOST_INFO_USER_ID'],
                    'goods_name' => $v['LOST_GOODS_NAME'],
                    'status' => $v['LOST_STATUS'],
                    'owner_remark' => $v['LOST_GOODS_REMARK'],
                    'reply_time' => $v['LOST_REPLY_TIME'],
                    'info_type' => $v['LOST_INFO_TYPE'],
                    'create_at' => date('Y-m-d H:i:s', substr($v['LOST_INFO_DATE']['time'], 0, -3)),
                ];
            }

            $pager = $jsonArr['pagemanager'];

            return [
                'total_list' => $pager['totalCount'],
                'current_page' => $pager['pageNo'],
                'total_page' => ceil($pager['totalCount'] / $page_size),
                'lists_size' => count($list),
                'lists' => $list,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * portal profile
     *
     * @return void
     */
    public function profile()
    {
        $response = $this->auth_dcp_client->post('dcp/profile/profile.action', [
            'json' => [
                'javaClass' => 'java.util.HashMap',
                'map' => [
                    'method' => 'getInfo',
                    'params' => null
                ]
            ],
            'headers' => [
                'render' => 'json',
                'clientType' => 'json',
            ]
        ]);

        $html = $response->getBody()->getContents();

        try {
            $jsonArr = json_decode($html, true);

            if (empty($jsonArr['list'][0])) {
                return [];
            }
            $list = $jsonArr['list'][0]['map'];

            return [
                'student_id' => $list['ID_NUMBER'],
                'name' => $list['USER_NAME'],
                'sex' => $list['USER_SEX'],
                'sex_name' => $list['SEX_NAME'],
                'birth_day' => str_replace('|', '-', $list['BIRTHDAY']),
                'user_id' => $list['USER_ID'],
                'unit_name' => $list['UNIT_NAME'],
                'mobile' => $list['MOBILE'],
                'id_type' => $list['ID_TYPE'],
                'id_type_name' => $list['ID_TYPE_NAME'],
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * portal cas link
     *
     * @return string
     */
    public function portal_cas_link()
    {
        return $this->cas_authority_link($this->get_service_by_name('portal'));
    }
    /**
     * education system cas link
     *
     * @return string
     */
    public function education_cas_link()
    {
        return $this->cas_authority_link($this->get_service_by_name('education'));
    }
    /**
     * elective system cas link
     *
     * @return string
     */
    public function elective_cas_link()
    {
        return $this->cas_authority_link($this->get_service_by_name('elective'));
    }
    /**
     * library system cas link
     *
     * @return string
     */
    public function library_cas_link()
    {
        return $this->cas_authority_link($this->get_service_by_name('library'));
    }

    /**
     * get cas authority link url
     *
     * @param string $service
     * @return string
     */
    public function cas_authority_link($service_cas_url = '')
    {
        $cache_handler = new FilesystemCache('cas.ecjtu.edu.cn.cas');

        if (!$cache_handler->has($this->cache_key)) {
            $response = $this->a_client->get('cas/login');
            $html = $response->getBody()->getContents();
            /**
             * get lt token
             */
            $lt_token = $this->get_login_lt_token($html);
            /**
             * get encrypt password
             */
            $enc_password = $this->get_encrypted_password($this->password);

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
            $cookies = $response->getHeader('Set-Cookie');
            $cookies_string = join(' ', $cookies);
            $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);
            $cache_handler->set($this->cache_key, $cookies_string, 1800);
        } else {
            $response = $this->a_client->get('cas/login?service=' . urlencode($service_cas_url), [
                'headers' => [
                    'Cookie' => $cache_handler->get($this->cache_key),
                ]
            ]);
            $html = $response->getBody()->getContents();

            if (preg_match('/用户登录.*初始密码为：身份证后6位/is', $html)) {
                throw new \Exception("Username or password incorrected", 401);
            }
        }

        $crawler = new Crawler($html);
        $cas_link = $crawler->filter('a')->attr('href');

        return $cas_link;
    }
    /**
     * uid
     *
     * @return string
     */
    public function uid()
    {
        $this->uid = md5(sha1($this->username . $this->password));
        return $this->uid;
    }
    /**
     * portal data
     *
     * @return void
     */
    protected function get_portal_data()
    {
        $response = $this->auth_dcp_client->post('dcp/getPortalData?sPage=home&gId=null&user_id=null&cid=null&template_type=2', [
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        ]);

        $html = $response->getBody()->getContents();

        try {
            $jsonArr = json_decode($html, true);

            return $jsonArr;
        } catch (\Exception $e) {
            return null;
        }
    }
    /**
     * get dcp home session id
     *
     * @return string
     */
    protected function dcp_home_session_id()
    {
        $cache_handler = new FilesystemCache('portal.ecjtu.edu.cn.dcp');

        if ($cache_handler->has($this->cache_key)) {
            return $cache_handler->get($this->cache_key);
        }

        $cas_link = $this->cas_authority_link('http://portal.ecjtu.edu.cn/dcp/index.jsp');
        $response = $this->a_client->get($cas_link);
        $cookies = $response->getHeader('Set-Cookie');
        $html = $response->getBody()->getContents();
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);
        $cache_handler->set($this->cache_key, $cookies_string, 1800);

        return $cookies_string;
    }
    /**
     * get login lt-token
     *
     * @param string $html
     * @return string
     */
    protected function get_login_lt_token($html = '')
    {
        try {
            $crawler = new Crawler($html);
            $lt_token = $crawler->filter('input[name=lt]')->attr('value');

            return $lt_token;
        } catch (\Exception $e) {
            return '';
        }
    }
    /**
     * get encrpted password
     *
     * @param string $password
     * @return string or null (failed)
     */
    protected function get_encrypted_password($password = '')
    {
        if (!$password) {
            return null;
        }

        $response = $this->a_client->post('cas/loginPasswdEnc', [
            'form_params' => [
                'pwd' => $password
            ]
        ]);

        $html = $response->getBody()->getContents();
        $jsonArr = json_decode($html, true);

        return empty($jsonArr['passwordEnc']) ? null : $jsonArr['passwordEnc'];
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
            throw new \Exception("Username or Password is needed", 1);
        }

        $this->username = $user['username'];
        $this->password = $user['password'];
    }
    /**
     * init handler
     *
     * @return void
     */
    protected function init_client_handler()
    {
        $this->a_client = new Client([
            'base_uri' => self::CAS_BASE_URI,
            'timeout' => 5,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            ]
        ]);

        $this->auth_dcp_client = new Client([
            'base_uri' => self::DCP_BASE_URI,
            'timeout' => 5,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
                'Cookie' => $this->dcp_home_session_id(),
            ]
        ]);
    }
    /**
     * get service authority url by name
     *
     * @param string $service_name
     * @return string
     */
    protected function get_service_by_name($service_name = '')
    {
        if (!$service_name) {
            return null;
        }

        $services = [
            'education' => 'http://jwxt.ecjtu.jx.cn/stuMag/Login_dcpLogin.action',
            'elective' => 'http://xkxt.ecjtu.jx.cn/login/login_dcpLogin.action',
            'library' => 'http://lib1.ecjtu.jx.cn/goldwsdl/login.aspx',
            'portal' => 'http://portal.ecjtu.edu.cn/dcp/index.jsp',
        ];

        return empty($services[$service_name]) ? null : $services[$service_name];
    }

}
