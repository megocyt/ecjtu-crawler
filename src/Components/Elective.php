<?php
namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\ElectiveInterface;
use Megoc\Ecjtu\Components\Login;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Overtrue\Pinyin\Pinyin;
use function GuzzleHttp\json_decode;

/**
 * Elective
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
class Elective implements ElectiveInterface
{
    protected $baseUrl = 'http://xkxt.ecjtu.jx.cn/';
    protected $username;
    protected $password;
    protected $clientHandler;

    /**
     * User
     *
     * @var [type]
     */
    protected $user;
    

    public function __construct(array $user)
    {
        $this->set_user($user);
        $this->clientHandler = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 5,
            'headers' => [
                'Cookie' => $this->login(),
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            ],
        ]);
    }
    /**
     * Get selected course
     *
     * @param string $term
     * @return void
     */
    public function course(string $term = '')
    {
        $uri = $term ? 'common/common_getSelectedCourses.action?term=' . $term : 'common/common_selectedCourse.action';

        $response = $this->clientHandler->get($uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);

        $courses = [];
        $title   = [];
        $pinyin  = new Pinyin();

        $crawler->filter('tr th')->each(function (Crawler $node, $i) use (&$title, &$pinyin)
        {
            $title[$i] = join( '', $pinyin->convert( trim( $node->text() ) ) );
        });

        $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$courses, &$title, &$pinyin)
        {
            if ($i == 0) return ;

            $course = [];

            $node->filter('td')->each(function (Crawler $node, $i) use (&$course, &$title, &$pinyin)
            {
                $course[$title[$i]] = trim($node->text());
            });
            unset($course[$title[count($title) - 1]]);
            $courses[] = $course;
        });

        return $courses;
    }
    /**
     * Public course list
     *
     * @return void
     */
    public function publicCourseList()
    {
        $uri = 'commonXK/commonXK_getCoureTeachTask.action';

        $response = $this->clientHandler->get($uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);

        $courses       = [];
        $title         = [];
        $pinyin        = new Pinyin();
        $teach_task_id = [];
        /**
         * get total page
         */
        preg_match('/<input id="pageTotal" type="hidden" value="(\d*)".*>/iUs', $html, $pages);

        if (!empty($pages)) {
            $page = $pages[1];
        }
        /**
         * get title info
         */
        $crawler->filter('tr th')->each(function (Crawler $node, $i) use (&$title, &$pinyin)
        {
            $title[$i] = join( '', $pinyin->convert( trim( $node->text() ) ) );
        });

        $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$courses, &$title, &$pinyin)
        {
            if ($i == 0) return ;

            $course = [];

            $node->filter('td')->each(function (Crawler $node, $i) use (&$course, &$title, &$pinyin)
            {
                if ($i == 8) {
                    preg_match('/teachTaskId=(\d+)/is', $node->html(), $matchs);

                    if (!empty($matchs)) {
                        $course['teachtaskid'] = $matchs[1];
                    } else {
                        $course['teachtaskid'] = '';
                    }
                }

                $course[$title[$i]] = trim($node->text());
            });

            unset($course[$title[count($title) - 1]]);
            $courses[] = $course;
        });
        /**
         * get all course 
         */
        for ($i=1; $i < $page; $i++) {
            $response = $this->clientHandler->get('commonXK/commonXK_getCoureTeachTask.action?currentPage=' . ($i+1));
            $html     = $response->getBody()->getContents();
            $crawler  = new Crawler($html);

            $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$courses, &$title, &$pinyin)
            {
                if ($i == 0) return ;

                $course = [];

                $node->filter('td')->each(function (Crawler $node, $i) use (&$course, &$title, &$pinyin)
                {
                    if ($i == 8) {
                        preg_match('/teachTaskId=(\d+)/is', $node->html(), $matchs);

                        if (!empty($matchs)) {
                            $course['teachtaskid'] = $matchs[1];
                        } else {
                            $course['teachtaskid'] = $matchs[1];
                        }
                    }

                    $course[$title[$i]] = trim($node->text());
                });
                unset($course[$title[count($title) - 1]]);
                $courses[] = $course;
            });
        }
        /**
         * get task id array
         */
        foreach ($courses as $key => $value) {
            $teach_task_id[$key] = $value['teachtaskid'];
        }
        /**
         * get select number
         */
        $response = $this->clientHandler->post('commonXK/commonXK_getSelCourseNum.action', [
            'form_params' => [
                'teachTaskIDs' => join(',', $teach_task_id)
            ]
        ]);
        $html    = $response->getBody()->getContents();
        $jsonArr = json_decode($html, true);

        foreach ($courses as $key => $value) {
            $courses[$key]['yixuanrenshu'] = $jsonArr[$key]['selStuNum'];
        }
        
        unset($pinyin);
        return $courses;
    }
    /**
     * Get teacher's resume
     *
     * @param string $teacher_task_id
     * @return void
     */
    public function teacherResume(string $teacher_task_id = '')
    {
        if (!$teacher_task_id) {
            return [];
        }

        $uri = 'Resume/Resume_iniEditResume.action?teachTaskId=' . $teacher_task_id;
        $response = $this->clientHandler->get($uri);
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);

        $resume = [];
        $title  = [];
        $pinyin = new Pinyin();

        try {
            $nopass = $crawler->filter('.not-pass')->html();
            return [];
        } catch (\Exception $e) {}

        try {
            $empty_con = trim($crawler->filter('.query-con')->text());
            
            if (!$empty_con) {
                return [];
            }
        } catch (\Exception $e) {}

        /**
         * get title info
         */
        $crawler->filter('tr td.k')->each(function (Crawler $node, $i) use (&$title, &$pinyin)
        {
            $title[$i] = join( '', $pinyin->convert( trim( $node->text() ) ) );
        });

        $crawler->filter('tr td.v')->each(function (Crawler $node, $i) use (&$resume, &$title)
        {
            $resume[$title[$i]] = trim($node->text());
        });
        /**
         * get description
         */
        $crawler->filter('div.cv-item div.items')->each(function (Crawler $node, $i) use (&$resume, &$title)
        {
            $text = str_replace('    ', '', $node->text());
            if ($i == 0) {
                $resume['jiaoxueqingkuang'] = trim($text);
            } else {
                $resume['keyanqingkuang'] = trim($text);
            }
        });

        /**
         * get name's pinyin
         */
        $resume['xingmingpinyin'] = join( '', $pinyin->convert( $resume['xingming'] ) );

        /**
         * get photo id
         */
        $teacher_id_string = trim($crawler->filter('.head-img-edit')->html());
        preg_match('/teacherID=(\d*)"/is', $teacher_id_string, $matchs);
        if (!empty($matchs)) {
            $resume['zhaopianbianhao'] = $matchs[1];
        } else {
            $resume['zhaopianbianhao'] = '';
        }

        return $resume;
    }
    /**
     * Get teacher's photo
     *
     * @param string $teacher_id
     * @param boolean $echo_string
     * @return void
     */
    public function teacherPhoto(string $teacher_id = '', $echo_string = true)
    {
        if (empty($teacher_id)) {
            return base64_encode(null);
        }

        $uri      = 'Resume/Resume_readPhoto.action?teacherID=' . $teacher_id;
        $response = $this->clientHandler->get($uri);
        $html     = $response->getBody()->getContents();

        return base64_encode($html);
    }
    /**
     * Profile
     *
     * @return array
     */
    public function profile()
    {
        $response = $this->clientHandler->get('index/index_getPersonalInfo.action');
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);

        $profile = [];
        $pinyin  = new Pinyin();

        $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$profile, &$pinyin)
        {
            $title = '';
            $node->filter('td')->each(function (Crawler $node, $i) use (&$profile, &$title, &$pinyin)
            {
                if ($i % 2 == 0) {
                    $title = join( '', $pinyin->convert( trim( $node->text() ) ) );
                    return ;
                } else {
                    $profile[$title] = trim($node->text());
                }
            });
        });

        unset($pinyin);

        return $profile;
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

        $LoginHandler = new Login;

        $login = $LoginHandler->verifyCode($this->baseUrl . 'servlet/code.servlet')->form([
            'username'       => $this->username,
            'password'       => $this->password,
            'verifyCodeName' => 'code',
        ])->login($this->baseUrl . 'login/login_checkout.action');

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
        $this->password = $user['password'];
        return false;
    }    
}
