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

        if (preg_match('/未评教完成，不能进行成绩查询！/iUs', $html)) {
            throw new UnassessException();
        }

        $crawler = new Crawler($html);
        $terms = [];
        $crawler->filter('.s_term li')->each(function (Crawler $node, $i) use (&$terms) {
            $terms[] = $node->text();
        });
        $scores = [];
        $crawler->filter('ul.term_score')->each(function (Crawler $node, $i) use (&$scores) {
            $score = [];
            $title = ['xq', 'course_name', 'course_require', 'check_type', 'credit', 'score', 'score_b', 'score_c', ];

            $node->filter('li')->each(function (Crawler $node, $i) use (&$score, &$title) {
                if ($i == 1) {
                    preg_match('/【(.*)】(.*)/is', $node->text(), $match);
                    $score['course_id'] = $match[1];
                    $score[$title[$i]] = trim($match[2]);
                } else {
                    $score[$title[$i]] = $node->text();
                }
            });
            $score['is_passed'] = self::is_passed($score['score'], $score['score_b'], $score['score_c']);
            $scores[] = $score;
        });

        $scores_tmp = [];
        $unexcept_str_rules = [
            '/　　*/is',
            '/  */is',
        ];
        foreach ($scores as $key => $value) {
            $term_t = $value['xq'];
            unset($value['xq']);
            ksort($value);
            $value['course_name'] = preg_replace($unexcept_str_rules, '', $value['course_name']);
            $scores_tmp[$term_t][] = $value;
        }

        $t = [];
        foreach ($scores_tmp as $k => $v) {
            $t[$k] = [
                'term' => $k,
                'lists' => $v,
            ];
        }

        if ($term) {
            return empty($t[$term]) ? [] : $t[$term];
        } else {
            return array_values($t);
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

        if (preg_match('/未评教完成，不能进行成绩查询！/iUs', $html)) {
            throw new UnassessException();
        }

        $crawler = new Crawler($html);

        $credit = [];
        $title = ['class_name', 'name', 'student_id', 'study_status', 'study_warning_status', 'study_warning_status', 'degree_avrage_credit_require', 'degree_avrage_credit_require', 'total_credit_require', 'total_credit_require', 'total_credit_require', 'common_credit', 'common_credit', 'common_credit', 'course_credit', 'course_credit', 'course_credit', 'major_credit', 'major_credit', 'major_credit', 'study_warning_status' => ['should', 'done'], 'degree_avrage_credit_require' => ['get', 'got'], 'total_credit_require' => ['got', 'get', 'own'], 'common_credit' => ['got', 'get', 'own'], 'course_credit' => ['got', 'get', 'own'], 'major_credit' => ['got', 'get', 'own'], ];

        $crawler->filter('.score-count tr')->each(function (Crawler $node, $i) use (&$credit, &$title) {
            $node->filter('td')->each(function (Crawler $node, $i) use (&$credit, &$title) {
                if ($i < 4) {
                    $credit[$title[$i]] = $node->text();
                } elseif ($i < 8) {
                    $n = $i % 2;
                    $credit[$title[($i - $n)]][$title[$title[($i - $n)]][$n]] = $node->text();
                } else {
                    $i++;
                    $n = $i % 3;
                    $credit[$title[($i - $n)]][$title[$title[($i - $n)]][$n]] = $node->text();
                }
            });
        });

        return $credit;
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
        $crawler = new Crawler($html);
        $username = '';
        $student_id = '';
        $th = [];
        $tr = [];
        $second_credit = [];

        $crawler->filter('.type_s1')->each(function (Crawler $node, $i) use (&$th) {
            $th[] = $node->text();
        });

        $crawler->filter('table tr')->each(function (Crawler $node, $i) use (&$tr, &$username, &$student_id) {
            if ($i == 0) return;

            $td = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$td) {
                $td[] = $node->text();
            });

            if (!empty($td)) {
                $username = $username ?: $td[1];
                $student_id = $student_id ?: $td[2];

                $tr[] = [
                    'scientific' => $td[2],
                    'art' => $td[3],
                    'volunteer' => $td[4],
                    'work' => $td[5],
                ];
            }
        });

        foreach ($th as $k => $v) {
            $second_credit[] = [
                'title' => str_replace('  ', ' ', $v),
                'credits' => $tr[$k]
            ];
        }

        return $second_credit;
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
        $crawler = new Crawler($html);
        $schedules = ['Mon' => [], 'Tues' => [], 'Wed' => [], 'Thur' => [], 'Fri' => [], 'Sat' => [], 'Sun' => [], ];
        $weeks = ['jieci', 'Mon', 'Tues', 'Wed', 'Thur', 'Fri', 'Sat', 'Sun', ];

        try {
            $term = $crawler->filter('select#term option[selected]')->attr('value');
        } catch (\Throwable $th) {
            $term = $term;
        }

        $crawler->filter('#courseSche tr')->each(function (Crawler $node, $i) use (&$schedules, &$weeks) {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return;

            if ($i == 0) return;

            $node->filter('td')->each(function (Crawler $node, $i) use (&$schedules, &$weeks) {
                $text = trim($node->html());
                $text = preg_replace('/(　)*/is', '', $text);
                $day = $weeks[$i];

                if (!$text) {
                    return;
                }

                if (preg_match('/^\d*-\d*$/is', $text)) {
                    $class_span = $text;
                    return;
                }

                $course = explode('<br>', $text);
                array_pop($course);

                if (count($course) % 3 != 0) {
                    if (count($course) == 2) {
                        preg_match('/(.*)/is', $course[0], $str1);
                        $str2 = explode(" ", str_replace('  ', ' ',  $course[1]));
                        $schedule = [
                            'course_name' => $course[0],
                            'teacher_name' => trim($str2[0]),
                            'address' => $str1[2] ?? '',
                            'weeek_span' => trim($str2[1]),
                            'class_span' => trim($str2[2]),
                        ];
                    } else {
                        preg_match('/(.*) @(.*)/is', $course[1], $str1);
                        preg_match('/(.*) (.*)/is', $course[2], $str2);
                        $schedule = [
                            'course_name' => $course[0],
                            'teacher_name' => trim($str1[1]),
                            'address' => trim($str1[2]),
                            'weeek_span' => trim($str2[1]),
                            'class_span' => trim($str2[2]),
                        ];
                    }
                    $schedules[$day][] = $schedule;
                } else {
                    for ($i = 0; $i < count($course) / 3; $i++) {
                        preg_match('/(.*) @(.*)/is', $course[$i * 3 + 1], $str1);
                        preg_match('/(.*) (.*)/is', $course[$i * 3 + 2], $str2);
                        $schedules[$day][] = [
                            'course_name' => $course[0],
                            'teacher_name' => trim($str1[1]),
                            'address' => trim($str1[2]),
                            'weeek_span' => trim($str2[1]),
                            'class_span' => trim($str2[2]),
                        ];
                    }
                }
            });
        });

        return [
            'term' => $term,
            'lists' => $schedules
        ];
    }
    /**
     * 周历
     *
     * @param string $week
     * @param string $term
     * @return array
     */
    public function week_schedule(string $week = '', string $term = '')
    {
        $uri = 'Schedule/Weekcalendar_getStudentWeekcalendar.action?week=' . $week;
        $uri .= $term ? '&term=' . $term : '';
        $response = $this->auth_client->get($uri);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        $week_schedules = ['Mon' => [], 'Tues' => [], 'Wed' => [], 'Thur' => [], 'Fri' => [], 'Sat' => [], 'Sun' => [], ];
        $weeks = ['jieci', 'Mon', 'Tues', 'Wed', 'Thur', 'Fri', 'Sat', 'Sun', ];
        $t = [];

        try {
            // 如果可以，从页面获取学期、周次信息
            $term = $crawler->filter('select#term option[selected]')->attr('value');
            $week = $crawler->filter('select#week option[selected]')->attr('value');
        } catch (\Throwable $th) { }

        // 处理课表页面，提取信息
        $crawler->filter('table#courseSche tr')->each(function (Crawler $node, $i) use (&$week_schedules, &$weeks, &$t) {
            if ($i == 0) return;

            $node->filter('td')->each(function (Crawler $node, $i) use (&$week_schedules, &$weeks, &$t) {
                // 过滤无用的字符
                $text = trim($node->html());
                $text = preg_replace('/\n*/is', '', $text);
                $text = preg_replace('/(	)*/is', '', $text);
                $text = preg_replace('/(　)*/is', '', $text);
                $day = $weeks[$i];

                if (strlen($text) == 18) {
                    return;
                }

                if (preg_match('/^\d*-\d*$/is', $text)) {
                    $class_span = $text;
                    return;
                }

                $course = explode('<br>', $text);
                $str1 = explode(' ', $course[1]);
                $course = [
                    'course_name' => $course[0],
                    'teacher_name' => trim($str1[0]),
                    'type' => trim(mb_substr($str1[1], 1)),
                    'address' => $course[2],
                    'class_name' => $course[3],
                ];
                ksort($course);
                $week_schedules[$day][] = $course;
            });
        });

        return [
            'week' => $week,
            'term' => $term,
            'lists' => $week_schedules
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
        $html = preg_replace('/\s{2,}|\n*|　*/is', '', $html);
        $crawler = new Crawler($html);
        $exams = ['term' => $term, 'lists' => []];
        $title = ['xueqi', 'course_name', 'course_type', 'class_name', 'capacity', 'week', 'address', 'address', 'class_number', ];

        $crawler->filter('.table_border tr')->each(function (Crawler $node, $i) use (&$exams, &$title) {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return;
            if ($i == 0) return;

            $exam = [];

            $node->filter('td')->each(function (Crawler $node, $i) use (&$exam, &$title) {
                $exam[$title[$i]] = $node->text();
            });

            $exams['term'] = $exam['xueqi'];
            unset($exam['xueqi']);
            ksort($exam);
            $exams['lists'][] = $exam;
        });

        return $exams;
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
        $html = preg_replace('/\s{2,}|\n*|　*/is', '', $html);
        $crawler = new Crawler($html);
        $exams = ['term' => $term, 'lists' => []];
        $title = ['xueqi', 'course_id', 'course_name', 'course_require', 'week', 'time', 'address', ];

        $crawler->filter('.table_border tr')->each(function (Crawler $node, $i) use (&$exams, &$title) {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return;
            if ($i == 0) return;

            $exam = [];

            $node->filter('td')->each(function (Crawler $node, $i) use (&$exam, &$title) {
                $exam[$title[$i]] = $node->text();
            });

            $exams['term'] = $exam['xueqi'];
            unset($exam['xueqi']);
            ksort($exam);
            $exams['lists'][] = $exam;
        });

        return $exams;
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
        $html = preg_replace('/\s{2,}|\n*|　*/is', '', $html);
        $crawler = new Crawler($html);

        $experiments = ['term' => $term, 'lists' => []];
        $title = ['xueqi', 'course_name', 'course_type', 'name', 'type', 'times', 'time', 'address', 'teacher_name', ];

        $crawler->filter('#dis-exam-info tr')->each(function (Crawler $node, $i) use (&$experiments, &$title) {
            if (preg_match('/对不起!没有当前学期的实验数据。/is', $node->text())) return;
            if ($i == 0) return;

            $experiment = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$experiment, &$title) {
                $experiment[$title[$i]] = trim($node->text());
            });

            ksort($experiment);
            $experiments['term'] = $experiment['xueqi'];
            unset($experiment['xueqi']);
            $experiments['lists'][] = $experiment;
        });

        return $experiments;
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

        if (!$class_id) return [];

        $response = $this->auth_client->post('infoQuery/class_findStuNames.action', [
            'form_params' => [
                'classInfo.classID' => $class_id,
            ],
        ]);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        /**
         * Mates
         */
        $mates = [];
        $title = ['number', 'name', 'sex', 'class_id', 'student_id', 'study_status', ];
        $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$mates, &$title) {
            if ($i == 0) return;

            $node->filter('td')->each(function (Crawler $node, $i) use (&$mates, &$title, &$mate) {
                $mate[$title[$i]] = $node->text();
            });

            ksort($mate);

            $mate['sex'] = $mate['sex'] == '女' ? 2 : 1;
            $mates[] = $mate;
        });

        return $mates;
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
        /**
         * save profile
         */
        $profile = [];
        $title = ['student_id', 'class_id', 'name', 'class_name', 'sex', 'nation', 'birthday', 'id_card', 'party', 'native_place', 'training_plan', 'english_level', 'study_status', 'punish_status', 'college_eaxm_number', 'college_eaxm_score', 'student_origin', ];

        $crawler->filter('#info_detail td.v')->each(function (Crawler $node, $i) use (&$profile, &$title) {
            $profile[$title[$i]] = trim($node->text());
        });

        unset($profile['id_card']);
        unset($profile['party']);

        if (!empty($profile['student_id']) && $profile['student_id'] == '2015031002000422') {
            $profile['sex'] = '女';
        }

        if (!empty($profile['sex'])) {
            $profile['sex'] = $profile['sex'] == '女' ? 2 : 1;
        }

        ksort($profile);

        return $profile;
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
        $html = preg_replace('/\s{2,}|\n*|　*/is', '', $html);
        $crawler = new Crawler($html);
        /**
         * numbers
         */
        $numbers = [];
        $title = ['xueqi', 'course_type', 'teach_class_name', 'course_name', 'require', 'check_type', 'period', 'credit', 'class_span', 'teacher_name', 'select_type', 'class_name', 'class_number', ];

        $crawler->filter('#dis-exam-info tbody tr')->each(function (Crawler $node, $i) use (&$numbers, &$title) {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return;
            $number = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$number, &$title) {
                if ($i == 2 || $i == 3) {
                    $tmp = trim($node->text());
                    $tmp = preg_replace('/　+/is', '', $tmp);
                    $number[$title[$i]] = $tmp;
                } else {
                    $number[$title[$i]] = trim($node->text());
                }
            });
            ksort($number);
            $numbers['term'] = $number['xueqi'];
            unset($number['xueqi']);
            $numbers['lists'][] = $number;
        });

        return $numbers;
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
        $class_list = [];

        $crawler->filter('option')->each(function (Crawler $node, $i) use (&$class_list) {
            if ($i == 0) return;

            $class_list[] = [
                'class_id' => $node->attr('value'),
                'class_name' => $node->text(),
            ];
        });

        return $class_list;
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
        $college_list = [];

        $crawler->filter('#departMent option')->each(function (Crawler $node, $i) use (&$college_list) {
            if ($i == 0) return;

            $college_list[] = $node->text();
        });

        return $college_list;
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
        $notifications = [];
        $crawler->filter('ul#remind-xk li')->each(function (Crawler $node, $i) use (&$notifications) {
            $a = $node->filter('a');
            $t = $node->filter('span');
            $href = $a->attr('href');
            preg_match('/id=(\d*)/is', $href, $matches);
            $notifications[] = [
                'resource_id' => $matches[1],
                'title' => $a->text(),
                'publish_at' => str_replace('年', '-', str_replace('月', '-', str_replace('日', '', $t->text()))),
            ];
        });

        return $notifications;
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
            return [
                'resource_id' => $resource_id,
                'title' => '没有找到此消息',
                'author' => '',
                'content' => '',
                'mod_content' => '',

            ];
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
}
