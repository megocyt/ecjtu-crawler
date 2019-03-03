<?php
/*
 * @Author: Megoc
 * @Date: 2019-01-19 13:06:10
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-02-13 11:07:20
 * @Email: megoc@megoc.org
 * @Description: Create by vscode
 */

namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Components\PortalCAS;
use Megoc\Ecjtu\Traits\EducationTrait;
use Megoc\Ecjtu\Interfaces\PortalInterface;
use Megoc\Ecjtu\Exceptions\UnauthorizedException;
use Megoc\Ecjtu\Exceptions\AccountIncorrectException;

class Portal implements PortalInterface
{
    use EducationTrait;
    /**
     * server url
     */
    const BASE_URI = 'http://portal.ecjtu.edu.cn/';

    /**
     * create instance
     *
     * @param array $user
     */
    public function __construct(array $user = [])
    {
        $this->init_cache_handler('portal.ecjtu.edu.cn');

        $this->init_http_client_handler();

        if (!empty($user['username']) && !empty($user['password'])) {
            $this->login($user);
        }
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
        $response = $this->auth_client->post('dcp/pim/pim.action', [
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
                    'is_top' => $v['IS_TOP'] ?? 0,
                    'resource_id' => $v['RESOURCE_ID'],
                    'belong_unit' => $v['BELONG_UNIT_NAME'],
                    'type_name' => $v['TYPE_NAME'],
                    'is_read' => $v['IS_READ'],
                    'title' => $v['PIM_TITLE'],
                    'content' => $v['PIM_CONTENT'] ?? '',
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

        $response = $this->auth_client->post('dcp/pim/pim.action', [
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
            $content_jsonp = $this->auth_client->get($url)->getBody()->getContents();
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
        $response = $this->auth_client->post('dcp/lost/lost.action', [
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
        $response = $this->auth_client->post('dcp/profile/profile.action', [
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
     * login 
     *
     * @param array $user
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

        $CAS = new PortalCAS([
            'username' => $this->username,
            'password' => $this->password
        ]);

        $cas_authority_link = $CAS->cas_authority_link($CAS->service_name2service_uri('portal'));

        $response = $this->a_client->get($cas_authority_link);
        $html = $response->getBody()->getContents();

        if (preg_match('/初始密码为：身份证后6位/is', $html)) {
            throw new AccountIncorrectException();
        }

        $cookies = $response->getHeader('Set-Cookie');
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);

        $this->cache_handler->set($this->uid(), $cookies_string, 1800);
        $this->init_http_client_handler($this->uid());
    }
    /**
     * cas authority
     *
     * @param string $uid
     * @param string $cas_authority_url
     * @return void
     */
    public function cas_authority(string $uid, string $cas_authority_url = '')
    {
        if (!$uid) {
            throw new \Exception("unique id is needed in cas authority!", -5);
        }

        if (!$cas_authority_url || !preg_match('/^http|https/is', $cas_authority_url)) {
            throw new \Exception("cas authority need a correct cas url!", -1);
        }

        $response = $this->a_client->get($cas_authority_url);
        $html = $response->getBody()->getContents();

        if (preg_match('/初始密码为：身份证后6位/is', $html)) {
            throw new AccountIncorrectException();
        }

        $cookies = $response->getHeader('Set-Cookie');
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);

        $this->cache_handler->set($uid, $cookies_string, 1800);
        $this->init_http_client_handler($uid);
    }
}
