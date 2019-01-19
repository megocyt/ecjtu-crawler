<?php
/*
 * @Author: Megoc 
 * @Date: 2019-01-17 09:37:42 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-01-17 15:11:34
 * @E-mail: megoc@megoc.org 
 * @Description: create by vscode 
 */

namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\ElectiveInterface;
use GuzzleHttp\Client;
use Symfony\Component\Cache\Simple\FilesystemCache;
use GuzzleHttp\Cookie\CookieJar;
use Megoc\Ecjtu\CodeOCR\EcjtuOCR;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Megoc\Ecjtu\Traits\EducationTrait;

class Elective implements ElectiveInterface
{
    use EducationTrait;
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
     * course list
     *
     * @param string $term
     * @return array
     */
    public function course(string $term = '')
    {
        $uri = $term ? 'common/common_getSelectedCourses.action?term=' . $term : 'common/common_selectedCourse.action';

        $response = $this->auth_client->get($uri);
        $html = $response->getBody()->getContents();
        $html = preg_replace('/\s{2,}|\n*/is', '', $html);
        $crawler = new Crawler($html);

        $courses = [];
        $title = ['xueqi', 'select_type', 'class_name', 'course', 'require', 'check_type', 'period', 'credit', 'class_span', 'teacher_name', 'course_type', 'course_plan', 'capacity', 'selected_number', 'action'];

        $crawler->filter('table#course-data tr')->each(function (Crawler $node, $i) use (&$courses, &$title, &$term) {
            if ($i == 0) return;

            $course = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$course, &$title, &$term) {
                $course[$title[$i]] = trim($node->text());

                if ($i == 9) {
                    $href = $node->filter('a')->attr('href');
                    preg_match('/teachTaskId=(.*)/is', $href, $matches);
                    $teacher_task_id = $matches[1];
                    $course['teacher_task_id'] = $teacher_task_id;
                }
            });

            $term = $course['xueqi'];
            unset($course['xueqi']);
            ksort($course);
            $courses[] = $course;
        });

        return [
            'term' => $term,
            'lists' => $courses
        ];
    }
    /**
     * profile
     *
     * @return array
     */
    public function profile()
    {
        $response = $this->auth_client->get('index/index_getPersonalInfo.action');
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        $profile = [];
        $title = ['name', 'sex', 'student_id', 'class_name', 'class_id', 'campus', 'rotc', 'english_level', 'study_warning', 'study_status', 'minor_degree_class_id', 'current_term', 'minor_degree_class_name', 'course_select_term', ];

        $crawler->filter('table#infoTable tr')->each(function (Crawler $node, $i) use (&$profile, $title) {
            $node->filter('td')->each(function (Crawler $node, $j) use (&$profile, $title, $i) {
                if ($j == 1) {
                    $profile[$title[$i * 2]] = trim($node->text());
                    return;
                }
                if ($j == 3) {
                    $profile[$title[$i * 2 + 1]] = trim($node->text());
                    return;
                }
            });
        });

        if ($profile['student_id'] == '2015031002000422') {
            $profile['sex'] = '女';
        }

        $profile['sex'] = $profile['sex'] == '女' ? 2 : 1;
        ksort($profile);

        return $profile;
    }
    /**
     * public course list
     *
     * @param string $page
     * @return array
     */
    public function public_course_list($page = '')
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
     * teacher resume
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

        if (preg_match('/教师个人简介暂未审核，无法查看！/is', $html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resume = [];
        $title = ['name', 'sex', 'nation', 'native_place', 'birth_day', 'belong_unit', 'party', 'technical_title', 'highest_education', 'highest_degree', 'position', 'admission_date', ];
        $avatar_img_url = $crawler->filter('img.head-img')->attr('src');
        $crawler->filter('td.v')->each(function (Crawler $node, $i) use (&$resume, $title) {
            $resume[$title[$i]] = $node->text();
        });
        $crawler->filter('div.cv-item')->each(function (Crawler $node, $i) use (&$resume, $title) {
            if ($i == 1) {
                $string = trim($node->html());
                $string = preg_replace('/\s{2,}|\n*/is', '', $string);
                $resume['teaching_situation'] = $string;
            }
            if ($i == 2) {
                $string = trim($node->html());
                $string = preg_replace('/\s{2,}|\n*/is', '', $string);
                $resume['scientific'] = $string;
            }
        });
        $phote_response = $this->auth_client->get($avatar_img_url);
        $resume['photo'] = base64_encode($phote_response->getBody()->getContents());
        ksort($resume);

        return $resume;
    }
    /**
     * select state
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
                throw new \Exception("Username or password is needed to login system!", -1);
            }
        } else {
            $this->set_user($user);
        }

        if ($this->cache_handler->has($this->uid())) {
            $this->init_http_client_handler($this->uid());
            return;
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
                $this->init_http_client_handler($this->uid());

                return;
            }
        }

        if (preg_match('/用户名或密码错误/is', $html)) {
            throw new \Exception("Username or password is incorrected!", -4);
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
                throw new \Exception("captcha recognition error, we had attempt $loop_times times, Please check program...", -8);
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

}
