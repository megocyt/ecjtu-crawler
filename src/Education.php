<?php
namespace Megoc\Ecjtu;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Education 
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
class Education implements EducationInterface
{
    /**
     * base url
     *
     * @var string
     */
    protected $baseUrl = 'http://jwxt.ecjtu.jx.cn/';
    /**
     * username
     *
     * @var [type]
     */
    protected $username;
    /**
     * password
     *
     * @var [type]
     */
    protected $password;
    /**
     * Http handler
     *
     * @var [type]
     */
    protected $clientHandler;


    public function __construct(array $user)
    {
        $this->set_user($user);
        $this->clientHandler = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 5,
            'headers' => [
                'Cookie' => $this->login(),
            ],
        ]);
    }
    /**
     * Score Query
     *
     * @param string $term
     * @return void
     */
    public function score(string $term='')
    {
        $response = $this->clientHandler->get('scoreQuery/stuScoreQue_getStuScore.action');
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);

        $terms = [];
        $crawler->filter('.s_term li')->each(function (Crawler $node, $i) use (&$terms)
        {
            $terms[] = $node->text();
        });
        /**
         * save all course score
         */
        $scores = [];

        $crawler->filter('ul.term_score')->each(function (Crawler $node, $i) use (&$scores)
        {
            /**
             * save single course score
             */
            $score = [];
            /**
             * title
             */
            $title = [
                'xq',
                'kcmc',
                'kcyq',
                'khfs',
                'kcxf',
                'kscj',
                'ckcj',
                'cxcj',
            ];

            $node->filter('li')->each(function (Crawler $node, $i) use (&$score, &$title)
            {
                if ($i == 1) {
                    preg_match('/【(.*)】(.*)/is', $node->text(), $match);

                    $score['kcbh']     = $match[1];
                    $score[$title[$i]] = trim($match[2]);
                } else {
                    $score[$title[$i]] = $node->text();
                }
            });
            /**
             * score pass status
             */
            $score['is_passed'] = self::is_passed($score['kscj'], $score['ckcj'], $score['cxcj']);

            $scores[] = $score;
        });

        $scores_tmp = [];

        foreach ($scores as $key => $value) {
            $scores_tmp[$value['xq']][] = $value;
        }

        return ($term && !empty($scores_tmp[$term])) ? $scores_tmp[$term] : $scores_tmp;
    }
    /**
     * Credit
     *
     * @param string $term
     * @return void
     */
    public function credit()
    {
        $response = $this->clientHandler->get('scoreQuery/stuScoreQue_getStuScore.action');
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);
        /**
         * credit array
         */
        $credit = [];
        /**
         * array key name
         */
        $title  = [
            'banji',
            'xingming',
            'xuehao',
            'xuejizhuangtai',
            'xueyeyujingqingkuang',
            'xueyeyujingqingkuang',
            'xueweipingjunxuefenjidianyaoqiu',
            'xueweipingjunxuefenjidianyaoqiu',
            'zongxuefenyaoqiu',
            'zongxuefenyaoqiu',
            'zongxuefenyaoqiu',
            'gonggongrenxuankexuefen',
            'gonggongrenxuankexuefen',
            'gonggongrenxuankexuefen',
            'xuekerenxuankexuefen',
            'xuekerenxuankexuefen',
            'xuekerenxuankexuefen',
            'zhuanyerenxuankexuefen',
            'zhuanyerenxuankexuefen',
            'zhuanyerenxuankexuefen',
            'xueyeyujingqingkuang'            => ['yingwancheng', 'yiwancheng'],
            'xueweipingjunxuefenjidianyaoqiu' => ['yinghuode', 'yihuode'],
            'zongxuefenyaoqiu'                => ['yingwancheng', 'yiwancheng', 'qianxuefen'],
            'gonggongrenxuankexuefen'         => ['yingwancheng', 'yiwancheng', 'qianxuefen'],
            'xuekerenxuankexuefen'            => ['yingwancheng', 'yiwancheng', 'qianxuefen'],
            'zhuanyerenxuankexuefen'          => ['yingwancheng', 'yiwancheng', 'qianxuefen'],
        ];

        $crawler->filter('.score-count tr')->each(function (Crawler $node, $i) use (&$credit, &$title)
        {
            $node->filter('td')->each(function (Crawler $node, $i) use (&$credit, &$title)
            {
                if ($i < 4) {
                    $credit[$title[$i]] = $node->text();
                } elseif ($i < 8) {
                    $n = $i%2;
                    $credit[$title[($i-$n)]][$title[$title[($i-$n)]][$n]] = $node->text();
                } else {
                    $i++;
                    $n = $i%3;
                    $credit[$title[($i-$n)]][$title[$title[($i-$n)]][$n]] = $node->text();
                }
            });
        });

        return $credit;
    }
    /**
     * Schedule
     *
     * @param string $term
     * @return void
     */
    public function schedule(string $term='')
    {
        $uri = 'Schedule/Schedule_getUserSchedume.action';

        $uri .= $term ? '?term=' . $term : '';

        $response = $this->clientHandler->get($uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);
        /**
         * schedume
         */
        $schedules = [];
        $weeks    = [
            'jieci',
            'Mon',
            'Tues',
            'Wed',
            'Thur',
            'Fri',
            'Sat',
            'Sun',
        ];
        $title = [
            'xueqi',
            'kechengmingcheng',
            'kechengxingzhi',
            'banjimingcheng',
            'xueshengrenshu',
            'kaoshizhouci',
            'kaoshishijian',
            'kaoshididian',
            'xiaobanbianhao',
        ];

        $crawler->filter('#courseSche tr')->each(function (Crawler $node, $i) use (&$schedules, &$title, &$weeks)
        {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return ;
            if ($i == 0) return;

            $schedule = [];
            $class_span = '';
            
            $node->filter('td')->each(function (Crawler $node, $i) use (&$schedules, &$schedule, &$title, &$class_span, &$weeks)
            {
                $text = trim($node->html());

                if (preg_match('/^\d*-\d*$/is', $text)) {
                    // if is class span, return;
                    $class_span = $text;
                    return ;
                } elseif (!$text) {
                    // if null string ,return
                    return ;
                } else {
                    $course = explode('<br>', $text);
                    $schedule['kechengmingcheng'] = $course[0];

                    $tmp = explode(' ', $course[1]);
                    $schedule['jiaoshi'] = trim($tmp[0]);
                    $schedule['didian']  = trim($tmp[1]);
                    $tmp = explode(' ', $course[2]);
                    if (count($tmp) != 1) {
                        $schedule['zhouci'] = trim($tmp[0]);
                        $schedule['jieci']  = trim($tmp[1]) ? : trim($tmp[2]);
                    }
                    
                }
                $schedules[$weeks[$i]][] = $schedule;
            });

        });

        return $schedules;
    }
    /**
     * daily calendar
     *
     * @param string $date
     * @return void
     */
    public function daily(string $date)
    {
        
        $response = $this->clientHandler->post('Schedule/Weekcalendar_getTodayWeekcalendar.action', [
            'form_params' => [
                'date' => $date,
            ],
        ]);
        $html    = $response->getBody()->getContents();
        $jsonArr = json_decode($html, true);

        $daily = [];
        $map   = [
            'className'     => 'class_name',
            'classRoom'     => 'class_room',
            'classSpan'     => 'class_span',
            'classString'   => 'class_span_string',
            'course'        => 'course',
            'courseRequire' => 'course_type',
            'pkType'        => 'type',
            'teacherName'   => 'teacher',
            'weekDay'       => 'day',
            'weekSpan'      => 'weeks',
        ];

        if (!empty($jsonArr)) {
            $daily['date']          = $jsonArr['date'];
            $daily['week']          = $jsonArr['dsWeek'];
            $daily['day']           = $jsonArr['weekDay'];
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
     * Exam Query
     *
     * @param string $term
     * @return void
     */
    public function exam(string $term='')
    {
        if (!$term) {
            $uri = 'examArrange/stuExam_findTerm.action';
        } else {
            $uri = 'examArrange/stuExam_stuQueryExam.action?userName=' . $this->username . '&term=' . $term;
        }

        $response = $this->clientHandler->get($uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);
        /**
         * Exam
         */
        $exams = [];
        $title = [
            'xueqi',
            'kechengmingcheng',
            'kechengxingzhi',
            'banjimingcheng',
            'xueshengrenshu',
            'kaoshizhouci',
            'kaoshishijian',
            'kaoshididian',
            'xiaobanbianhao',
        ];

        $crawler->filter('.table_border tr')->each(function (Crawler $node, $i) use (&$exams, &$title)
        {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return ;
            if ($i == 0) return;
            
            $exam = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$exam, &$title)
            {
                if ($i == 2 || $i == 3) {
                    $tmp = trim($node->text());
                    $tmp = preg_replace('/　+/is', '', $tmp);
                    $exam[$title[$i]] = $tmp;
                } else {
                    $exam[$title[$i]] = trim($node->text());
                }

            });
            $exams[] = $exam;
        });

        return $exams;
    }
    /**
     * Bexam
     *
     * @param string $term
     * @return void
     */
    public function bexam(string $term='')
    {
        $uri = 'examArrange/stuBKExam_stuBKExam.action';

        $uri .= $term ? '?term=' . $term : '';

        $response = $this->clientHandler->get($uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);
        /**
         * Exam
         */
        $exams = [];
        $title = [
            'xueqi',
            'kechengmingcheng',
            'kechengxingzhi',
            'banjimingcheng',
            'xueshengrenshu',
            'kaoshizhouci',
            'kaoshishijian',
            'kaoshididian',
            'xiaobanbianhao',
        ];

        $crawler->filter('.table_border tr')->each(function (Crawler $node, $i) use (&$exams, &$title)
        {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return ;
            if ($i == 0) return;
            
            $exam = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$exam, &$title)
            {
                if ($i == 2 || $i == 3) {
                    $tmp = trim($node->text());
                    $tmp = preg_replace('/　+/is', '', $tmp);
                    $exam[$title[$i]] = $tmp;
                } else {
                    $exam[$title[$i]] = trim($node->text());
                }

            });
            $exams[] = $exam;
        });

        return $exams;
    }
    
    public function empty_classroom(string $term='')
    {
        return ;
    }
    /**
     * Experiment
     *
     * @param string $term
     * @return void
     */
    public function experiment(string $term='')
    {
        $uri      = $term ? '?term=' . $term : '';
        $response = $this->clientHandler->get('Experiment/StudentExperiment_getExperiment.action' . $uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);

        $experiments = [];
        $title = [
            'xueqi',
            'kechengmingcheng',
            'kechengxingzhi',
            'shiyanmingcheng',
            'shiyanfangshi',
            'pici',
            'shijian',
            'shiyandidian',
            'jiaoshi',
        ];

        $crawler->filter('#dis-exam-info tr')->each(function (Crawler $node, $i) use (&$experiments, &$title)
        {
            if (preg_match('/对不起!没有当前学期的实验数据。/is', $node->text())) return ;
            if ($i == 0) return;
            
            $experiment = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$experiment, &$title)
            {
                if ($i == 2 || $i == 3) {
                    $tmp = trim($node->text());
                    $tmp = preg_replace('/　+/is', '', $tmp);
                    $experiment[$title[$i]] = $tmp;
                } else {
                    $experiment[$title[$i]] = trim($node->text());
                }

            });
            $experiments[] = $experiment;
        });

        return $experiments;
    }
    /**
     * class mates
     *
     * @return void
     */
    public function classmate(string $class_id='')
    {
        if (empty($class_id)) {
            $profile  = $this->profile();
            $class_id = substr($profile['zaibanbianhao'], 0, 14);            
        }

        if (!$class_id) return ;

        $response = $this->clientHandler->post('infoQuery/class_findStuNames.action', [
            'form_params' => [
                'classInfo.classID' => $class_id,
            ],
        ]);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);
        /**
         * Mates
         */
        $mates = [];
        $title = [
            'xuhao',
            'xingming',
            'xingbie',
            'zaibanbianhao',
            'xuehao',
            'xuejizhuangtai',
        ];

        $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$mates, &$title)
        {
            if ($i == 0) return ;
            $node->filter('td')->each(function (Crawler $node, $i) use (&$mates, &$title, &$mate)
            {
                $mate[$title[$i]] = $node->text();
            });
            $mates[] = $mate;
        });

        return $mates;
    }
    /**
     * Profile
     *
     * @return void
     */
    public function profile()
    {
        $response = $this->clientHandler->get('stuMag/UserInfoAction_findUserInfo.action');
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);
        /**
         * save profile
         */
        $profile = [];
        $title = [
            'xuehao',
            'zaibanbianhao',
            'xingming',
            'banji',
            'xingbie',
            'mingzu',
            'chushengriqi',
            'shenfenzheng',
            'zhengzhimianmao',
            'jiguan',
            'peiyangfangan',
            'yingyufenjijibie',
            'xuejizhuangtai',
            'chufenzhuangtai',
            'gaokaokaosheghao',
            'gaokaochengji',
            'shengyuandi',
        ];

        $crawler->filter('#info_detail td.v')->each(function (Crawler $node, $i) use (&$profile, &$title)
        {
            $profile[$title[$i]] = trim($node->text());
        });
        /**
         * unset 
         */
        unset($profile['shenfenzheng']);
        unset($profile['zhengzhimianmao']);
        return $profile;
    }
    /**
     * class number
     *
     * @param string $term
     * @return void
     */
    public function class_number(string $term='')
    {
        $uri = $term ? '?term=' . $term : '';
        $response = $this->clientHandler->get('infoQuery/XKStu_findTerm.action' . $uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);
        /**
         * numbers
         */
        $numbers = [];
        $title = [
            'xueqi',
            'xuankeleixing',
            'jiaoxuebanmingcheng',
            'kechengmingcheng',
            'kechengyaoqiu',
            'kaohefangshi',
            'xueshi',
            'xuefen',
            'shangkeshijian',
            'renkejiaoshi',
            'xuankeleixing',
            'xiabanmingcheng',
            'xiaobanxuhao',
        ];

        $crawler->filter('#dis-exam-info tbody tr')->each(function (Crawler $node, $i) use (&$numbers, &$title)
        {
            if (preg_match('/对不起!当前学期未查到相关/is', $node->text())) return ;
            $number = [];
            $node->filter('td')->each(function (Crawler $node, $i) use (&$number, &$title)
            {
                if ($i == 2 || $i == 3) {
                    $tmp = trim($node->text());
                    $tmp = preg_replace('/　+/is', '', $tmp);
                    $number[$title[$i]] = $tmp;
                } else {
                    $number[$title[$i]] = trim($node->text());
                }

            });
            $numbers[] = $number;
        });

        return $numbers;
    }
    /**
     * login
     *
     * @return void
     */
    protected function login()
    {
        if (empty($this->username) || empty($this->password)) {
            return '';
        }
        $LoginHandler = new \Megoc\Ecjtu\Login;
        $LoginHandler->username($this->username);
        $LoginHandler->password($this->password);

        $login = $LoginHandler->verifyCode($this->baseUrl . 'servlet/code.servlet')->login($this->baseUrl . 'stuMag/Login_login.action');

        return $login;
    }
    /**
     * Set User Login information
     *
     * @param array $user
     * @return bool
     */
    protected function set_user(array $user)
    {
        if ( empty($user) || empty($user['username']) || empty($user['password']) ) {
            return false;
        }

        $this->username = $user['username'];
        $this->password = md5($user['password']);
        return false;
    }
    /**
     * check score pass status
     *
     * @param string $score
     * @return boolean
     */
    protected static function is_passed(string $a_score='', string $b_score='', string $c_score='')
    {
        $score = $c_score ? : $b_score ? : $a_score;
        
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
