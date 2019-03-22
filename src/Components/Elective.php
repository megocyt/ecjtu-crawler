<?php
 /*
 * @Author: Megoc 
 * @Date: 2019-01-17 09:37:42 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-02-13 12:08:31
 * @E-mail: megoc@megoc.org 
 * @Description: create by vscode 
 */

namespace Megoc\Ecjtu\Components;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Megoc\Ecjtu\CodeOCR\EcjtuOCR;
use Megoc\Ecjtu\Traits\HelperTraits;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\RequestException;
use Megoc\Ecjtu\Exceptions\CaptchaException;
use Megoc\Ecjtu\Interfaces\ElectiveInterface;
use Megoc\Ecjtu\Exceptions\UnauthorizedException;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Megoc\Ecjtu\Exceptions\AccountIncorrectException;

class Elective implements ElectiveInterface
{
    use HelperTraits;
    /**
     * server base uri
     */
    const BASE_URI = 'http://xkxt.ecjtu.edu.cn/';

    /**
     * construct
     *
     * @param array $user
     */
    public function __construct(array $user = [])
    {
        $this->init_cache_handler('xkxt.ecjtu.edu.cn');

        $this->init_http_client_handler();

        if (!empty($user['username']) && !empty($user['password'])) {
            $this->login($user);
        }
    }
    /**
     * 选课信息
     *
     * @param string $term
     * @return array
     */
    public function course(string $term = '')
    {
        $uri = $term ? 'common/common_getSelectedCourses.action?term=' . $term : 'common/common_selectedCourse.action';

        $response = $this->auth_client->get($uri);

        $html = $response->getBody()->getContents();

        $table_array = $this->courseTable2Array($html);

        // 转为 key => value形式
        $items_assoc = [];

        foreach ($table_array as $item) {
            $term = $item[0];
            $items_assoc[] = [
                'course_id' => $item[17],
                'course_name' => $item[3],
                'course_type' => $item[1],
                'require' => $item[4],
                'check_type' => $item[5],
                'class_name' => $item[2],
                'period' => $item[6],
                'credit' => $item[7],
                'class_span' => $item[8],
                'teacher_name' => $item[9],
                'select_type' => $item[10],
                'course_plan' => $item[11],
                'capacity' => $item[12],
                'select_number' => $item[13],
                'teacher_task_id' => $item[16],
                'action' => $item[14],
            ];
        }

        return [
            'term' => $term,
            'lists' => $items_assoc
        ];
    }
    /**
     * 个人信息
     *
     * @return array
     */
    public function profile()
    {
        $response = $this->auth_client->get('index/index_getPersonalInfo.action');

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $items = $crawler->filter('table#infoTable tr td')->each(function (Crawler $node, $n) {
            return trim($node->text());
        });

        $items_assoc = [
            'name' => $items[1],
            'sex' => $items[3] == '女' ? 2 : 1,
            'student_id' => $items[5],
            'class_name' => $items[7],
            'class_id' => $items[9],
            'campus' => $items[11],
            'english_level' => $items[13],
            'is_rotc' => $items[14] == '国防生' ? 1 : 0,
            'study_status' => $items[17],
            'study_warning' => $items[19],
            'minor_degree_class_id' => $items[23],
            'minor_degree_class_name' => $items[27],
        ];

        if ($items_assoc['student_id'] == '2015031002000422') {
            $items_assoc['sex'] = 2;
        }

        return $items_assoc;
    }
    /**
     * public course list
     *
     * @param int $page
     * @return array
     */
    public function public_course_list($page = 1)
    {
        $uri = 'commonXK/commonXK_getCoureTeachTask.action?currentPage=' . $page;

        try {
            $response = $this->auth_client->get($uri);

            $html = $response->getBody()->getContents();
        } catch (ServerException $e) {
            $this->auth_client->get('xkNotice_getXKNoticeInfo.action?xkSelectType=4&eduType=1');

            $response = $this->auth_client->get($uri);

            $html = $response->getBody()->getContents();
        }

        $crawler = new Crawler($html);
        $courses = [];
        $title = ['xueqi', 'class_name', 'course', 'course_category', 'check_type', 'period', 'credit', 'class_span', 'teacher_name', 'capacity', 'selected_number', 'action'];
        $current_page = $crawler->filter('input#pageCurrent')->attr('value');
        $total_page = $crawler->filter('input#pageTotal')->attr('value');

        $crawler->filter('table#course-data tr')->each(function (Crawler $node, $i) use (&$courses, &$title, &$term) {
            if ($i == 0) return;

            $course = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$course, &$title, &$term) {
                $course[$title[$i]] = trim($node->text());

                if ($i == 1) {
                    $course['teacher_task_id'] = $node->filter('input')->attr('value');
                }
                if ($i == 2) {
                    $course['course_id'] = $node->filter('input')->attr('value');
                }
            });

            $term = $course['xueqi'];
            unset($course['xueqi']);
            ksort($course);
            $courses[] = $course;
        });

        $task_ids = [];

        foreach ($courses as $value) {
            $task_ids[] = $value['teacher_task_id'];
        }

        if (!empty($task_ids)) {
            $response = $this->auth_client->post('commonXK/commonXK_getSelCourseNum.action', [
                'form_params' => [
                    'teachTaskIDs' => join(',', $task_ids),
                ]
            ]);
            $html = $response->getBody()->getContents();
            $jsonArr = json_decode($html, true);
            foreach ($jsonArr as $key => $value) {
                $courses[$key]['selected_number'] = $value['selStuNum'];
            }
        }

        return [
            'term' => $term,
            'total_page' => $total_page,
            'current_page' => $current_page,
            'list_size' => count($courses),
            'lists' => $courses
        ];
    }
    /**
     * 教师简历
     *
     * @param string $teacher_id
     * @return array
     */
    public function teacher_resume(string $teacher_id = '')
    {
        if (!$teacher_id) {
            return [];
        }

        $response = $this->auth_client->get('Resume/Resume_iniEditResume.action?teachTaskId=' . $teacher_id);

        $html = $response->getBody()->getContents();

        try {
            if (preg_match('/教师个人简介暂未审核，无法查看！/is', $html)) {
                throw new \Exception("没有简历信息", 1);
            }

            $crawler = new Crawler($html);
            // 获取头像
            $photo_uri = $crawler->filter('img.head-img')->attr('src');

            $photo_response = $this->auth_client->get($photo_uri);
            // 将头像转为base64编码
            if ($photo_response->getStatusCode() == 200) {
                $photo = base64_encode($photo_response->getBody()->getContents());
            } else {
                $photo = null;
            }

            $items = $crawler->filter('td.v')->each(function (Crawler $node, $n) {
                return trim($node->text());
            });

            $cv_items = $crawler->filter('.cv-item div.items')->each(function (Crawler $node, $n) {
                return trim($node->html());
            });

            return [
                'name' => $items[0],
                'sex' => $items[1],
                'photo' => $photo,
                'nation' => $items[2],
                'birth_day' => $items[4],
                'belong_uint' => $items[5],
                'party' => $items[6],
                'teacher_level' => $items[7],
                'highest_degree' => $items[8],
                'highest_education' => $items[9],
                'position' => $items[10],
                'admission_at' => $items[11],
                'teaching' => $cv_items[0],
                'scientific' => $cv_items[1],
            ];

            return $items;
        } catch (\Throwable $th) {
            return [];
        }
    }
    /**
     * 选课状态
     *
     * @return array
     */
    public function course_select_info()
    {
        $response = $this->auth_client->post('index/index_getXKIndexInfo.action', [
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36'
            ]
        ]);
        $html = $response->getBody()->getContents();
        $jsonArr = json_decode($html, true);

        if (empty($jsonArr)) {
            return [];
        }

        $t = array_shift($jsonArr);
        $info = [];

        foreach ($t['listSelCourDate'] as $key => $value) {
            $info['course_select_open_list'][] = [
                'course_select_type' => $value['courseSelectType'],
                'open_date' => $value['startDate'],
                'close_date' => $value['endDate'],
                'term' => $value['term'],
                'select_past_day' => $value['pastSelCourDay'],
                'select_span_day' => $value['selCourseDay']
            ];
        }

        $info['course_select_state'] = [
            'obligatory_total' => $t['listSelCourState'][0]['biXuanAllNum'],
            'obligatory_undone' => $t['listSelCourState'][0]['biXuanWeiXuan'],
            'obligatory_done' => $t['listSelCourState'][0]['biXuanYiXuan'],
            'common_total' => $t['listSelCourState'][0]['commonSelectedNum'],
            'minor_total' => $t['listSelCourState'][0]['fxYingXuanAllNum'],
            'minor_done' => $t['listSelCourState'][0]['fxYingXuanYiXuan'],
            'optional_total' => $t['listSelCourState'][0]['keXuanAllNum'],
            'optional_done' => $t['listSelCourState'][0]['keXuanYiXuan'],
            'term' => $t['listSelCourState'][0]['term'],
        ];

        return $info;
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

        /**
         * get captcha image stream and code
         */
        $response = $this->a_client->get(self::BASE_URI . 'servlet/code.servlet');
        $captcha_image = $response->getBody()->getContents();
        $captcha_ocr = new EcjtuOCR($captcha_image);
        $captcha_code = $captcha_ocr->result();
        $cookies = $response->getHeader('Set-Cookie');
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);
        /**
         * send login request
         */
        $response = $this->a_client->post('login/login_checkout.action', [
            'form_params' => [
                'username' => $this->username,
                'password' => $this->password,
                'code' => $captcha_code,
            ],
            'headers' => [
                'Cookie' => $cookies_string,
            ],
            'allow_redirects' => false
        ]);

        $html = $response->getBody()->getContents();
        $locations = $response->getHeader('Location');

        if (!empty($locations)) {
            list($location) = $locations;

            $response = $this->a_client->get($location, [
                'headers' => [
                    'Cookie' => $cookies_string
                ]
            ]);

            $html = $response->getBody()->getContents();
            /**
             * test login result
             */
            if (!preg_match('/如何才能使用微信扫一扫登录/is', $html)) {
                $this->cache_handler->set($this->uid(), $cookies_string, 900);
                return $this->init_http_client_handler($this->uid());
            }
        }

        if (preg_match('/用户名或密码错误/is', $html)) {
            throw new AccountIncorrectException();
        }

        /**
         * if is captcha error, attempt more
         */
        if (preg_match('/验证码错误/is', $html)) {
            static $loop_times = 0;
            $loop_times++;

            if ($loop_times < 3) {
                $this->login();
            } else {
                throw new CaptchaException("captcha recognition error, we had attempt 3 times, Please check program...");
            }
        }
    }
    /**
     * cas authority
     *
     * @param string $uid
     * @param string $cas_link
     * @return void
     */
    public function cas_authority(string $uid, string $cas_link = '')
    {
        if (!$uid) {
            throw new \Exception("Uninque id is needed!", 1);
        }
    }
    /**
     * 将已选课程表格转为数组
     *
     * @param string $html
     * @return array
     */
    private function courseTable2Array(string $html)
    {
        // 将表格数据转为数组
        $items = [];

        try {
            if (preg_match('/本学期你还未选修任何课程！/is', $html)) {
                throw new \Exception("没有数据。", 1);
            }

            $crawler = new Crawler($html);

            $items = $crawler->filter('#course-data tr')->each(function (Crawler $node, $n) {
                if ($n == 0) {
                    return null;
                }
                // 获取单个课程信息
                $item = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                    return trim($nodec->text());
                });
                // 获取隐藏的表单信息
                $inputs = $node->filter('input')->each(function (Crawler $nodec, $nc) {
                    return trim($nodec->attr('value'));
                });

                return array_merge($item, $inputs);
            });

            $items = array_values(array_filter($items));
        } catch (\Throwable $th) {
            $items = [];
        }

        return $items;
    }
}
