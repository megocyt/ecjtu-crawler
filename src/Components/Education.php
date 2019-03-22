<?php
 /* 
 * @Author: Megoc 
 * @Date: 2019-01-14 09:44:39 
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-02-13 11:53:21
 * @E-mail: megoc@megoc.org 
 * @Description: Create by vscode 
 */

namespace Megoc\Ecjtu\Components;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Megoc\Ecjtu\CodeOCR\EcjtuOCR;
use Megoc\Ecjtu\Traits\HelperTraits;
use Symfony\Component\DomCrawler\Crawler;
use Megoc\Ecjtu\Exceptions\CacheException;
use Megoc\Ecjtu\Exceptions\CaptchaException;
use Megoc\Ecjtu\Exceptions\UnassessException;
use Megoc\Ecjtu\Interfaces\EducationInterface;
use Megoc\Ecjtu\Exceptions\UnauthorizedException;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Megoc\Ecjtu\Exceptions\AccountIncorrectException;

class Education implements EducationInterface
{
    use HelperTraits;
    /**
     * education server base uri
     */
    const BASE_URI = 'http://jwxt.ecjtu.jx.cn/';

    /**
     * 实例化对象
     *
     * @param array $user
     */
    public function __construct(array $user = [])
    {
        $this->init_cache_handler('jwxt.ecjtu.jx.cn');

        $this->init_http_client_handler();

        if (!empty($user['username']) && !empty($user['password'])) {
            $this->login($user);
        }
    }
    /**
     * 成绩信息
     *
     * @param string $term
     * @return array
     */
    public function score(string $term = '')
    {
        $response = $this->auth_client->get('scoreQuery/stuScoreQue_getStuScore.action');

        $html = $response->getBody()->getContents();

        try {
            if (preg_match('/未评教完成，不能进行成绩查询！/iUs', $html)) {
                throw new UnassessException();
            }

            $crawler = new Crawler($html);
            // 获取数据
            $items = $crawler->filter('.s_termScore ul')->each(function (Crawler $node, $n) {
                if ($n == 0) {
                    return null;
                }

                $item = $node->filter('li')->each(function (Crawler $nodec, $nc) {
                    return trim($nodec->text());
                });

                return $item;
            });
            $items = array_values(array_filter($items));

            // 按需要 key=>value 组织数据
            $items_assoc_tmp = [];

            foreach ($items as $item) {
                preg_match('/【(\d*)】(.*)/is', $item[1], $matches);
                $items_assoc_tmp[$item[0]][] = [
                    'course_id' => $matches[1],
                    'course_name' => $matches[2],
                    'course_require' => $item[2],
                    'check_type' => $item[3],
                    'credit' => $item[4],
                    'score_a' => $item[5],
                    'score_b' => $item[6],
                    'score_c' => $item[7],
                    'is_passed' => self::is_passed($item[5], $item[6], $item[7]),
                ];
            }

            $items_assoc = [];

            foreach ($items_assoc_tmp as $term_key => $lists) {
                $items_assoc[] = [
                    'term' => $term_key,
                    'lists' => $lists,
                ];

                if ($term == $term_key) {
                    return [
                        'term' => $term_key,
                        'lists' => $lists,
                    ];
                }
            }

            return $term ? [] : $items_assoc;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    /**
     * 学分信息
     *
     * @return array
     */
    public function credit()
    {
        $response = $this->auth_client->get('scoreQuery/stuScoreQue_getStuScore.action');

        $html = $response->getBody()->getContents();

        try {
            if (preg_match('/未评教完成，不能进行成绩查询！/iUs', $html)) {
                throw new UnassessException();
            }

            $crawler = new Crawler($html);
            // 获取数据
            $items = $crawler->filter('table.personal-socre-tab tr td')->each(function (Crawler $node, $n) {
                return trim($node->text());
            });
            // 按需要 key=>value 组织数据
            $items_assoc[] = [
                'class_name' => $items[0],
                'name' => $items[1],
                'student_id' => $items[2],
                'study_status' => $items[3],
                'study_warning_status' => [
                    'should' => $items[4],
                    'done' => $items[5],
                ],
                'degree_avrage_credit_require' => [
                    'get' => $items[6],
                    'got' => $items[7],
                ],
                'total_credit_require' => [
                    'get' => $items[8],
                    'got' => $items[9],
                    'own' => $items[10],
                ],
                'common_credit' => [
                    'get' => $items[11],
                    'got' => $items[12],
                    'own' => $items[13],
                ],
                'course_credit' => [
                    'get' => $items[14],
                    'got' => $items[15],
                    'own' => $items[16],
                ],
                'major_credit' => [
                    'get' => $items[17],
                    'got' => $items[18],
                    'own' => $items[19],
                ],
            ];

            return $items_assoc;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    /**
     * 第二课堂学分
     *
     * @return array
     */
    public function second_credit()
    {
        $response = $this->auth_client->get('scoreQuery/secondCreQue_findSecondCredit.action');

        $html = $response->getBody()->getContents();

        try {
            $crawler = new Crawler($html);
            // 获取标题
            $titles = $crawler->filter('.type_s1')->each(function (Crawler $node, $n) {
                return trim($node->text());
            });
            // 获取数据
            $items = $crawler->filter('table tr')->each(function (Crawler $node, $n) {
                $item = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                    return trim($nodec->text());
                });

                return $item;
            });
            // 剔除空数组
            $items = array_values(array_filter($items));
            // 按需要 key=>value 组织数据
            foreach ($items as $key => $item) {
                $items_assoc[] = [
                    'title' => $titles[$key],
                    'credits' => [
                        'scientific' => floatval($item[2]) ?: 0,
                        'art' => floatval($item[3]) ?: 0,
                        'volunteer' => floatval($item[4]) ?: 0,
                        'work' => floatval($item[5]) ?: 0,
                    ]
                ];
            }

            return $items_assoc;
        } catch (\Throwable $th) {
            return [];
        }
    }
    /**
     * 课表
     *
     * @param string $term
     * @return array
     */
    public function schedule(string $term = '')
    {
        $uri = 'Schedule/Schedule_getUserSchedume.action';

        $uri .= $term ? '?term=' . $term : '';

        $response = $this->auth_client->get($uri);

        $html = $response->getBody()->getContents();

        $schedule_table_arr = $this->scheduleTable2Array($html);

        $schedules = [];

        // 转为需要的数据返回格式
        foreach ($schedule_table_arr as $day => $items) {
            $schedules[$day] = [];
            foreach ($items as $span => $item) {
                switch (count($item)) {
                    case 3:
                        $spans1 =  explode(' ', $item[1]);
                        $spans2 = explode(' ', str_replace('  ', ' ', $item[2]));

                        $schedules[$day][] = [
                            'course_name' => trim($item[0]),
                            'teacher_name' => trim($spans1[0]),
                            'address' => trim($spans1[1]),
                            'week_span' => trim($spans2[0]),
                            'class_span' => trim($spans2[1]),
                        ];
                        break;

                    case 2:
                        // 没有上课地点的课程处理
                        $spans = explode(' ', str_replace('  ', ' ', $item[1]));

                        $schedules[$day][] = [
                            'course_name' => trim($item[0]),
                            'teacher_name' => trim($spans[0]),
                            'address' => null,
                            'week_span' => trim($spans[1]),
                            'class_span' => trim($spans[2]),
                        ];
                        break;

                    default:
                        // 处理可能存在单双周情况，或者分周次上课情况
                        if (count($item) % 3 == 0) {
                            for ($i = 0; $i < count($item) / 3; $i++) {
                                $spans1 =  explode(' ', $item[$i * 3 + 1]);
                                $spans2 = explode(' ', str_replace('  ', ' ', $item[$i * 3 + 2]));

                                $schedules[$day][] = [
                                    'course_name' => trim($item[$i * 3]),
                                    'teacher_name' => trim($spans1[0]),
                                    'address' => trim($spans1[1]),
                                    'week_span' => trim($spans2[0]),
                                    'class_span' => trim($spans2[1]),
                                ];
                            }
                        }
                        break;
                }
            }
        }

        return [
            'term' => $term,
            'lists' => $schedules
        ];
    }
    /**
     * 周历
     *
     * @param string|int $week
     * @param string $term
     * @return array
     */
    public function week_schedule(string $week = '', string $term = '')
    {
        $uri = 'Schedule/Weekcalendar_getStudentWeekcalendar.action?week=' . $week;

        $uri .= $term ? '&term=' . $term : '';

        $response = $this->auth_client->get($uri);

        $html = $response->getBody()->getContents();

        $schedule_table_arr = $this->scheduleTable2Array($html);

        $schedules = [];

        // 转为需要的数据返回格式
        foreach ($schedule_table_arr as $day => $items) {
            $schedules[$day] = [];
            foreach ($items as $span => $item) {
                switch (count($item)) {
                    case 4:
                        $spans1 = explode(' ', $item[1]);

                        $schedules[$day][] = [
                            'address' => $item[2],
                            'class_name' => $item[3],
                            'course_name' => $item[0],
                            'teacher_name' => $spans1[0],
                            'type' => $spans1[1],
                        ];
                        break;

                    case 3:
                        // 没有上课地点的课程处理
                        $spans1 = explode(' ', $item[1]);

                        $schedules[$day][] = [
                            'address' => null,
                            'class_name' => $item[3],
                            'course_name' => $item[0],
                            'teacher_name' => $spans1[0],
                            'type' => $spans1[1],
                        ];
                        break;

                    default:
                        // 
                        break;
                }
            }
        }

        return [
            'week' => $week,
            'term' => $term,
            'lists' => $schedules
        ];
    }
    /**
     * 日历
     *
     * @param string $date
     * @return void
     */
    public function daily(string $date = '')
    {
        $response = $this->auth_client->post('Schedule/Weekcalendar_getTodayWeekcalendar.action', [
            'form_params' => [
                'date' => $date ?: date('Y-m-d'),
            ],
        ]);
        $html = $response->getBody()->getContents();
        $jsonArr = json_decode($html, true);
        $daily = [];
        $map = ['className' => 'class_name', 'classRoom' => 'class_room', 'classSpan' => 'class_span', 'classString' => 'class_span_string', 'course' => 'course', 'courseRequire' => 'course_type', 'pkType' => 'type', 'teacherName' => 'teacher', 'weekDay' => 'day', 'weekSpan' => 'weeks', ];

        if (!empty($jsonArr)) {
            $daily['date'] = $jsonArr['date'];
            $daily['week'] = $jsonArr['week'];
            $daily['ds_week'] = $jsonArr['dsWeek'];
            $daily['day'] = $jsonArr['weekDay'];
            $daily['calendar_list'] = [];
            foreach ($jsonArr['weekcalendarpojoList'] as $key => $value) {
                foreach ($value as $k => $v) {
                    $daily['calendar_list'][$key][$map[$k]] = $v;
                }

                unset($daily['calendar_list'][$key]['class_span_string']);
            }
        }

        return $daily;
    }
    /**
     * 考试安排
     *
     * @param string $term
     * @return array
     */
    public function exam(string $term = '')
    {
        if (!$term) {
            $uri = 'examArrange/stuExam_findTerm.action';
        } else {
            $uri = 'examArrange/stuExam_stuQueryExam.action?userName=' . $this->username . '&term=' . $term;
        }

        $response = $this->auth_client->get($uri);

        $html = $response->getBody()->getContents();

        $table_array = $this->dataTable2Array($html, '对不起!当前学期未查到相关的考试安排项。');

        $exams = [];
        // 将原始数组转为需要的key => value格式
        foreach ($table_array as $item) {
            $term = $item[0];

            $exams[] = [
                'course_name' => $item[1],
                'course_type' => $item[2],
                'class_name' => $item[3],
                'capacity' => $item[4],
                'week' => $item[5],
                'time' => $item[6],
                'address' => $item[7],
                'class_number' => $item[8],
            ];
        }

        return [
            'term' => $term,
            'lists' => $exams,
        ];
    }
    /**
     * 补考安排
     *
     * @param string $term
     * @return array
     */
    public function bexam(string $term = '')
    {
        $uri = 'examArrange/stuBKExam_stuBKExam.action';

        $uri .= $term ? '?term=' . $term : '';

        $response = $this->auth_client->get($uri);

        $html = $response->getBody()->getContents();

        $table_array = $this->dataTable2Array($html, '对不起!当前学期未查到相关的考试安排项。');

        $exams = [];
        // 将原始数组转为需要的key => value格式
        foreach ($table_array as $item) {
            $term = $item[0];

            $exams[] = [
                'course_id' => $item[1],
                'course_name' => $item[2],
                'course_require' => $item[3],
                'week' => $item[4],
                'time' => $item[5],
                'address' => $item[6],
            ];
        }

        return [
            'term' => $term,
            'lists' => $exams,
        ];
    }
    /**
     * 实验安排
     *
     * @param string $term
     * @return array
     */
    public function experiment(string $term = '')
    {
        $uri = $term ? '?term=' . $term : '';

        $response = $this->auth_client->get('Experiment/StudentExperiment_getExperiment.action' . $uri);

        $html = $response->getBody()->getContents();

        $table_array = $this->dataTable2Array($html, '对不起!没有当前学期的实验数据。', '.table_border tr', [
            'tr' => [
                'attr' => 'style',
                'true' => 1,
                'false' => 0,
                'condition' => 'background:#eee;',
            ]
        ]);

        // 将原始数组转为需要的key => value格式
        $items_assoc = [];

        foreach ($table_array as $item) {
            $term = $item[0];

            $items_assoc[] = [
                'course_name' => $item[1],
                'course_type' => $item[2],
                'name' => $item[3],
                'type' => $item[4],
                'times' => $item[5],
                'time' => $item[6],
                'address' => $item[7],
                'teacher_name' => $item[8],
                'is_date_expired' => $item[9],
            ];
        }

        return [
            'term' => $term,
            'lists' => $items_assoc
        ];
    }
    /**
     * 班级名单
     *
     * @param string $class_id
     * @return array
     */
    public function classmate(string $class_id = '')
    {
        if (empty($class_id)) {
            $profile = $this->profile();
            $class_id = substr($profile['class_id'], 0, 14);
        }

        if (!$class_id) {
            return [];
        }

        $response = $this->auth_client->post('infoQuery/class_findStuNames.action', [
            'form_params' => [
                'classInfo.classID' => $class_id,
            ],
        ]);

        $html = $response->getBody()->getContents();

        $table_array = $this->dataTable2Array($html, 'notPrint');

        // 将原始数组转为需要的key => value格式
        $items_assoc = [];

        foreach ($table_array as $item) {
            $items_assoc[] = [
                'number' => $item[0],
                'name' => $item[1],
                'sex' => $item[2] == '女' ? 2 : 1,
                'class_id' => $item[3],
                'student_id' => $item[4],
                'study_status' => $item[5],
            ];
        }

        return $items_assoc;
    }
    /**
     * 个人资料
     *
     * @return void
     */
    public function profile()
    {
        $response = $this->auth_client->get('stuMag/UserInfoAction_findUserInfo.action');

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $items = $crawler->filter('#info_detail td.v')->each(function (Crawler $node, $in) {
            return trim($node->text());
        });

        return [
            'name' => $items[2],
            'sex' => $items[4] == '女' ? 2 : 1,
            'student_id' => $items[0],
            'course_id' => $items[1],
            'class_name' => $items[3],
            'nation' => $items[5],
            'birth_day' => $items[6],
            'party' => $items[8],
            'english_level' => $items[11],
            'study_status' => $items[12],
            'warning_status' => $items[13],
        ];
    }
    /**
     * 小班序号
     *
     * @param string $term
     * @return array
     */
    public function class_number(string $term = '')
    {
        $uri = $term ? '?term=' . $term : '';

        $response = $this->auth_client->get('infoQuery/XKStu_findTerm.action' . $uri);

        $html = $response->getBody()->getContents();

        $table_array = $this->dataTable2Array($html, '对不起!当前学期未查到相关的考试安排项。');

        // 将原始数组转为需要的key => value格式
        $items_assoc = [];

        foreach ($table_array as $item) {
            $items_assoc[] = [
                'course_type' => $item[1],
                'class_name' => $item[2],
                'course_name' => $item[3],
                'require' => $item[4],
                'check_type' => $item[5],
                'period' => $item[6],
                'credit' => $item[7],
                'class_span' => $item[8],
                'teacher_name' => $item[9],
                'select_type' => $item[10],
                'teach_class_name' => $item[11],
                'class_number' => $item[12],
            ];
        }

        return [
            'term' => $term,
            'lists' => $items_assoc
        ];
    }
    /**
     * 班级列表
     *
     * @param string $college
     * @param string $grade
     * @return array
     */
    public function class_list(string $college = '', string $grade = '')
    {
        if (empty($grade)) {
            $grade = date('Y');
        }

        if (empty($college)) {
            return [];
        }

        $response = $this->auth_client->post('infoQuery/class_findClaByDepGra.action', [
            'form_params' => [
                'depInfo.departMent' => $college,
                'gra.grade' => $grade,
            ]
        ]);

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        // 提取班级列表
        $items = $crawler->filter('option')->each(function (Crawler $node, $n) {
            if ($n == 0) {
                return null;
            }

            return [
                'class_id' => $node->attr('value'),
                'class_name' => $node->text(),
            ];
        });

        $items = array_values(array_filter($items));

        return $items;
    }
    /**
     * 学院列表
     *
     * @return array
     */
    public function college_list()
    {
        $response = $this->auth_client->get('infoQuery/class_findClassList.action');

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $items = $crawler->filter('#departMent option')->each(function (Crawler $node, $n) {
            if ($n == 0) {
                return null;
            }

            return trim($node->text());
        });

        $items = array_values(array_filter($items));

        return $items;
    }
    /**
     * CAS认证
     *
     * @param string $uid
     * @param string $cas_link
     * @return void
     */
    public function cas_authority(string $uid, string $cas_link = '')
    {
        if (!$uid) {
            throw new CacheException("unique id is needed in cas authority!", -5);
        }

        if (!$cas_link) {
            $this->init_http_client_handler($uid);
            return;
        }

        $response = $this->a_client->get($cas_link, [
            'allow_redirects' => false,
        ]);
        $html = $response->getBody()->getContents();
        $cookies = $response->getHeader('Set-Cookie');
        $cookies_string = join(' ', $cookies);
        $cookies_string = preg_replace('/path=.*; HttpOnly/is', '', $cookies_string);

        if ($response->getStatusCode() == 302) {
            $locations = $response->getHeader('Location');
            $location = array_shift($locations);
        } else {
            throw new \Exception("Cas authority failed!", 400);
        }

        $response = $this->a_client->get($location, [
            'headers' => [
                'Cookie' => $cookies_string,
            ]
        ]);
        $html = $response->getBody()->getContents();

        if (preg_match('/本系统无此用户，请联系所在学院。/is', $html)) {
            throw new \Exception("Cas authority failed!", 400);
        }

        $this->cache_handler->set($uid, $cookies_string, 900);
        $this->init_http_client_handler($uid);
    }
    /**
     * 登录系统
     * 传入用账号信息，模拟登录教务系统
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
        $response = $this->a_client->post('stuMag/Login_login.action', [
            'form_params' => [
                'UserName' => $this->username,
                'Password' => md5($this->password),
                'code' => $captcha_code,
            ],
            'headers' => [
                'Cookie' => $cookies_string,
            ]
        ]);
        $html = $response->getBody()->getContents();

        /**
         * test login result
         */
        if ($html == 'success') {
            $this->cache_handler->set($this->uid(), $cookies_string, 900);
            $this->init_http_client_handler($this->uid());

            return;
        }

        if ($html == '用户名或密码错误') {
            throw new AccountIncorrectException();
        }
        /**
         * if is captcha error, attempt more
         */
        if ($html == '验证码错误') {
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
     * 教务系统首页信息
     *
     * @return array
     */
    public function info()
    {
        $response = $this->auth_client->get('jwIndex/jwIndex_getIndexInfo.action');
        $html = $response->getBody()->getContents();

        $jsonArr = json_decode($html, true);
        return [
            'student_id' => $jsonArr[0]['userID'],
            'name' => $jsonArr[0]['name'],
            'class' => $jsonArr[0]['className'],
            'term' => $jsonArr[0]['term'],
            'study_status' => $jsonArr[0]['studyFlagCaption'],
        ];
    }
    /**
     * 教务系统通知列表
     *
     * @param integer $page
     * @return array
     */
    public function notifications(int $page = 1)
    {
        $response = $this->auth_client->get('jwIndex/jwIndex_listXKNotice.action?currentPage=' . $page);

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $items = $crawler->filter('#postList li')->each(function (Crawler $node, $n) {
            $a = $node->filter('a');

            $t = $node->filter('span');

            preg_match('/id=(\d*)/is', $a->attr('href'), $matches);

            return [
                'resource_id' => $matches[1],
                'title' => $a->text(),
                'publish_at' => str_replace('年', '-', str_replace('月', '-', str_replace('日', '', $t->text()))),
            ];
        });

        return $items;
    }
    /**
     * 通知详细内容
     *
     * @param string $resource_id
     * @return array
     */
    public function notification_detail(string $resource_id = '')
    {
        if (!$resource_id) {
            return [];
        }

        $response = $this->auth_client->get('jwIndex/jwIndex_detailNotice.action?xkNotice.id=' . $resource_id);

        $html = $response->getBody()->getContents();

        if (preg_match('/对不起，暂无通知信息！/is', $html)) {
            return [];
        }

        $crawler = new Crawler($html);

        $title = $crawler->filter('div#details h2')->first()->text();

        preg_match('/发布人：(.*)/is', $crawler->filter('div#details p.au-info')->text(), $matches);
        $author = $matches[1];
        $content = $crawler->filter('div#details div.context')->html();

        $mod_content = '<h2 style="padding: 15px 0;text-align: center;font-size: 24px;color: #0698E9;">' . $crawler->filter('div#details h2')->html() . '</h2>';
        $mod_content .= '<p style="text-align: center;margin-bottom: 10px;">' . $crawler->filter('div#details p.au-info')->html() . '</p><p style="height: 3px;background: #22b14c;"></p>';
        $mod_content .= $content;

        return [
            'resource_id' => $resource_id,
            'title' => $title,
            'author' => $author,
            'content' => $content,
            'mod_content' => $mod_content,
        ];
    }
    /**
     * 检查分数是否及格
     *
     * @param string $score
     * @return boolean
     */
    protected static function is_passed(string $a_score = '', string $b_score = '', string $c_score = '')
    {
        $score = $c_score ?: $b_score ?: $a_score;

        if (is_numeric($score)) {
            return $score < 60 ? 0 : 1;
        } else {
            if (preg_match('/及格|良好|中等|优秀/is', $score)) {
                return 1;
            } else {
                return 0;
            }
        }
    }
    /**
     * 下载课表
     *
     * @param string $term
     * @return array|null
     */
    public function download_schedule($term = '')
    {
        $query = array(
            'term' => $term,
        );

        $uri = 'Schedule/Schedule_download.action?' . http_build_query($query);

        $response = $this->auth_client->get($uri);

        if ($response->getStatusCode() != 200) {
            return null;
        }

        return array(
            'file_type' => join('', $response->getHeader('Content-Type')),
            'file_name' => join('', $response->getHeader('Content-disposition')),
            'file_stream' => $response->getBody()->getContents()
        );
    }
    /**
     * 下载班级名单
     *
     * @param string $class_id
     * @return array|null
     */
    public function download_classmate($class_id = '')
    {
        $uri = 'infoQuery/class_print.action';
        // 如果没有传入class_id，则默认获取当前登录账号的class_id
        if (!$class_id) {
            $profile = $this->profile();

            $class_id = $profile['class_id'];
        }
        // 如果传入的参数长度为16，即学生在班编号，则默认截取前14位即班级编号
        if (is_numeric($class_id) && strlen($class_id) == 16) {
            $class_id = substr($class_id, 0, 14);
        }

        $response = $this->auth_client->post($uri, [
            'form_params' => [
                // 'depInfo.departMent' => '土木建筑学院',
                // 'gra.grade' => '2018',
                'classInfo.classID' => $class_id,
            ],

        ]);

        if ($response->getStatusCode() != 200) {
            return null;
        }

        return array(
            'file_type' => join('', $response->getHeader('Content-Type')),
            'file_name' => join('', $response->getHeader('Content-disposition')),
            'file_stream' => $response->getBody()->getContents()
        );
    }
    /**
     * 学校教学楼列表
     *
     * @return array
     */
    public function teaching_buildings()
    {
        $uri = 'Schedule/ClassRoom_iniClassRoom.action?item=0204';

        $response = $this->auth_client->get($uri);

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $buildings = [];

        $buildings = $crawler->filter('#classinfo li a')->each(function (Crawler $node, $n) {
            try {
                return trim($node->attr('title'));
            } catch (\Throwable $th) {
                return null;
            }
        });

        return $buildings;
    }
    /**
     * 教学楼教室列表
     *
     * @param string $building
     * @return array
     */
    public function teaching_building_rooms($building)
    {
        $query = array(
            'building' => $building,
            'roomType' => '',
        );

        $uri = 'Schedule/ClassRoom_getClassRoomInfo.action?' . http_build_query($query);

        $response = $this->auth_client->get($uri);

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $rooms = [];

        $rooms = $crawler->filter('#data-center .reinfo .table_border tr')->each(function (Crawler $node, $n) {
            if ($n == 0) {
                return null;
            }

            $room_tmp = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                try {
                    return trim($nodec->text());
                } catch (\Throwable $th) {
                    return null;
                }
            });

            $room = [];

            if (count($room_tmp) == 5) {
                $room = [
                    'room_name' => $room_tmp[0],
                    'building_name' => $room_tmp[1],
                    'room_type' => $room_tmp[2],
                    'room_seats' => $room_tmp[3],
                    'room_eaxm_seats' => $room_tmp[4],
                ];
            }

            return $room;
        });
        // 剔除空数组
        $rooms = array_values(array_filter($rooms));

        return $rooms;
    }
    /**
     * 教室课表
     *
     * @param string $room
     * @param integer $week
     * @param string $term
     * @param string $building
     * @return array
     */
    public function teaching_building_room_schedule($room, $week = 1, $term = null, $building = null)
    {
        $query = array(
            'classRoom' => $room,
            'building' => $building,
            'zhouci' => $week ?? 1,
            'term' => $term,
        );

        if (!$query['term']) {
            unset($query['term']);
        }

        $uri = 'Schedule/KSchedule_findClassUseCase.action';

        $response = $this->auth_client->post($uri, [
            'form_params' => $query
        ]);

        $html = $response->getBody()->getContents();

        $schedule_table_arr = $this->scheduleTable2Array($html);
        $schedules = [];

        foreach ($schedule_table_arr as $day => $items) {
            $schedules[$day] = [];

            foreach ($items as $item) {
                switch (count($item)) {
                    case 4:
                        $schedules[$day][] = [
                            'class_name' => $item[0],
                            'course_name' => $item[1],
                            'class_span' => $item[2],
                            'teacher_name' => $item[3],
                        ];

                        break;

                    default:
                        // 
                        break;
                }
            }
        }

        return $schedules;
    }
    /**
     * 将课表按原表格样式转为数组形式
     *
     * @param string $html
     * @return array|null
     */
    private function scheduleTable2Array($html)
    {
        try {
            $crawler = new Crawler($html);

            $schedules = $crawler->filter('#courseSche tr')->each(function (Crawler $node, $n) {
                if ($n == 0) {
                    return null;
                }

                $schedule_tmp = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                    if ($nc == 0) {
                        return $nodec->text();
                    }

                    $td = $nodec->html();

                    $tds = explode('<br>', trim($td));
                    $tds = array_filter(array_map(function ($var) {
                        return trim($var);
                    }, $tds));

                    // 剔除只有一个元素的数组，其实是一个干扰，无法通过替换等操作去除
                    return count($tds) == 1 ? [] : array_values($tds);
                });

                $schedule = array_values(array_filter($schedule_tmp, function ($var) {
                    return is_array($var);
                }));
                return $schedule;
            });
            // 剔除空数组
            $schedules = array_values(array_filter($schedules));

            $maps = [
                'Mon', 'Tues', 'Wed', 'Thur', 'Fri', 'Sat', 'Sun',
            ];

            // 将原始数组转为day => schedule形式
            $schedules_assoc = [];
            foreach ($schedules[0] as $key => $value) {
                $schedules_assoc[$maps[$key]] = [
                    $schedules[0][$key],
                    $schedules[1][$key],
                    $schedules[2][$key],
                    $schedules[3][$key],
                    $schedules[4][$key],
                    $schedules[5][$key],
                ];
            }

            return $schedules_assoc;
        } catch (\Throwable $th) {
            return [];
        }
    }
    /**
     * 将数据表格转为数组
     *
     * @param string $html
     * @param string $data_is_null 没有数据时的匹配内容
     * @param string $table_tag
     * @param array $options
     * @return array
     */
    private function dataTable2Array(string $html, string $data_is_null, string $table_tag = '.table_border tr',  array $options = [])
    {
        // 将表格数据转为数组
        $items = [];

        try {
            if (preg_match('/' . $data_is_null . '/is', $html)) {
                throw new \Exception("没有数据。", 1);
            }

            $crawler = new Crawler($html);

            $items = $crawler->filter($table_tag)->each(function (Crawler $node, $n) use ($options) {
                if ($n == 0) {
                    return null;
                }

                $item = $node->filter('td')->each(function (Crawler $nodec, $nc) {
                    return trim($nodec->text());
                });

                if ($options) {
                    foreach ($options as $tag => $rule) {
                        $item[] = $node->filter($tag)->attr($rule['attr']) == $rule['condition'] ? $rule['true'] : $rule['false'];
                    }
                }

                return $item;
            });

            $items = array_values(array_filter($items));
        } catch (\Throwable $th) {
            $items = [];
        }

        return $items;
    }
}
