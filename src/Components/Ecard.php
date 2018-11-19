<?php
namespace Megoc\Ecjtu\Components;

use Megoc\Ecjtu\Interfaces\EcardInterface;
use Megoc\Ecjtu\Components\Login;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use function GuzzleHttp\Promise\each;


class Ecard implements EcardInterface
{
    protected $baseUrl = 'http://ecard.ecjtu.jx.cn/';
    

    protected $user;
    protected $clientHandler;

    public function __construct(array $user)
    {
        if ($this->set_user($user)) {
            # code...
        }

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
     * use profile
     *
     * @return array
     */
    public function account()
    {
        $response = $this->clientHandler->get('accountcardUser.action');
        $html     = $response->getBody()->getContents();
        $crawler  = new Crawler($html);

        $values = [];

        $crawler->filter('tr td.neiwen')->each(function (Crawler $node, $i) use (&$values)
        {
            $values[$i] = trim($node->text());
        });

        if (empty($values)) {
            return [];
        }

        $profile = [
            'xingming'         => $values[1],
            'zhanghu'          => $values[3],
            'xingbie'          => $values[6],
            'yue'              => $values[46],
            'xuehao'           => $values[8],
            'shenfenleixing'   => $values[12],
            'suoshubumen'      => $values[28],
            'suoshuxiaoqu'     => $values[31],
            'kazhuangtai'      => $values[48],
            'jianchazhuangtai' => $values[50],
        ];

        return $profile;
    }
    /**
     * Get user photo
     *
     * @param boolean $echo_string
     * @return string or bit
     */
    public function photo(bool $echo_string = true)
    {
        $response = $this->clientHandler->get('getPhoto.action');
        $html     = $response->getBody()->getContents();

        if ($echo_string) {
            return base64_encode($html);
        } else {
            return $html;
        }
    }
    /**
     * Today's trade
     *
     * @return void
     */
    public function trade()
    {
        $account = $this->accountNumber();
        $response = $this->clientHandler->post('accounttodatTrjnObject.action', [
            'form_params' => [
                'account'     => $account,
                'inputObject' => 'all',
            ],
        ]);
        $html    = $response->getBody()->getContents();
        $html    = iconv('gb2312', 'utf-8', $html);
        $crawler = new Crawler($html);

        echo $crawler->html();//exit;
        $ietms = [];
        $title = [
            'jiaoyishijian',
            'xuehao',
            'xingming',
            'jiaoyileixing',
            'shanghumingcheng',
            'jiaoyijine',
            'xianyouyue',
            'shuakacishu',
            'zhuangtai',
            'shuoming',
        ];

        $crawler->filter('table.dangrichaxun tr')->each(function (Crawler $node, $i) use (&$items, $title)
        {
            if ($i == 0 || preg_match('/共涉及/is', $node->text())) return ;
            $item = [];

            $node->filter('td')->each(function (Crawler $node, $i) use (&$item, $title)
            {
                $item[$title[$i]] = trim($node->text());
            });
            $items[] = $item;
        });

        $pages_string = $crawler->filter('tr.bl')->last()->html();
        preg_match('/(\d+)次交易.*交易额为:(.*)（元）.*共(\d+)页.*当前第(\d+)页/is', $pages_string, $pages);
        if (!empty($pages)) {
            $total_times  = $pages[1];
            $total_trade  = $pages[2];
            $current_page = $pages[3];
            $total_page   = $pages[4];
        }

        return [
            'zhanghu'     => $account,
            'jiaoyijine'  => $total_trade,
            'jiaoyicishu' => $total_times,
            'jiaoyijilu'  => $items,
        ];
    }
    /**
     * History trades
     * $start_date can be date string like 2018-01-01 or a number that started before number's day ago
     *
     * @param string $start_date
     * @param string $end_date
     * @return void
     */
    public function trades(string $start_date='', string $end_date='')
    {
        $account = $this->accountNumber();

        if (is_numeric($start_date)) {
            $start_date = date('Ymd', strtotime('-' . $start_date . ' day'));
        }
        /**
         * first step send query account an query type
         */
        $response1 = $this->clientHandler->post('accounthisTrjn1.action', [
            'form_params' => [
                'account'     => $account,
                'inputObject' => 'all',
            ],
        ]);
        /**
         * second step send query start date and end date
         */
        $response2 = $this->clientHandler->post('accounthisTrjn2.action', [
            'form_params' => [
                'inputStartDate' => $start_date ? $start_date : date('Ymd' , strtotime('-1 day')),
                'inputEndDate'   => $end_date ? $end_date : date('Ymd'),
            ],
        ]);
        /**
         * get first page of result
         */
        $response3 = $this->clientHandler->post('accounthisTrjn3.action');
        $html      = $response3->getBody()->getContents();
        $html      = iconv('gb2312', 'utf-8', $html);
        $crawler   = new Crawler($html);

        $ietms = [];
        $title = [
            'jiaoyishijian',
            'xuehao',
            'xingming',
            'jiaoyileixing',
            'shanghumingcheng',
            'jiaoyijine',
            'xianyouyue',
            'shuakacishu',
            'zhuangtai',
            'shuoming',
        ];

        $crawler->filter('table.dangrichaxun tr')->each(function (Crawler $node, $i) use (&$items, $title)
        {
            if ($i == 0 || preg_match('/本次查询共涉及/is', $node->text())) return ;
            $item = [];

            $node->filter('td')->each(function (Crawler $node, $i) use (&$item, $title)
            {
                $item[$title[$i]] = trim($node->text());
            });
            $items[] = $item;
        });
        /**
         * get page information
         */
        $pages_string = $crawler->filter('tr.bl')->last()->html();
        preg_match('/(\d+)次交易.*总计交易额为:(.*)（元）.*共(\d+)页.*当前第(\d+)页/is', $pages_string, $pages);
        if (!empty($pages)) {
            $total_times  = $pages[1];
            $total_trade  = $pages[2];
            $total_page   = $pages[3];
            $current_page = $pages[4];
        }
        /**
         * loop to get other pages record
         */
        for ($i=2; $i <= $total_page; $i++) {
            $response4 = $this->clientHandler->post('accountconsubBrows.action', [
                'form_params' => [
                    'inputStartDate' => $start_date ? $start_date : date('Ymd' , strtotime('-1 day')),
                    'inputEndDate'   => $end_date ? $end_date : date('Ymd'),
                    'pageNum'        => $i,
                ],
            ]);

            $html      = $response4->getBody()->getContents();
            $html      = iconv('gb2312', 'utf-8', $html);
            $crawler   = new Crawler($html);

            $crawler->filter('table.dangrichaxun tr')->each(function (Crawler $node, $i) use (&$items, $title)
            {
                if ($i == 0 || preg_match('/本次查询共涉及/is', $node->text())) return ;
                $item = [];

                $node->filter('td')->each(function (Crawler $node, $i) use (&$item, $title)
                {
                    $item[$title[$i]] = trim($node->text());
                });
                $items[] = $item;
            });
        }

        /**
         * return array
         */
        $return_record = [
            'zhanghu'       => $account,
            'jiaoyijine'    => $total_trade,
            'jiaoyicishu'   => $total_times,
            'xiaofeicishu'  => 0,
            'xiaofei'       => 0,
            'chongzhicishu' => 0,
            'chongzhi'      => 0,
        ];

        foreach ($items as $key => $value) {
            if ($value['jiaoyijine'] > 0) {
                $return_record['chongzhicishu'] += 1;
                $return_record['chongzhi']      += $value['jiaoyijine'];
            } else {
                $return_record['xiaofeicishu'] += 1;
                $return_record['xiaofei']      += $value['jiaoyijine'];
            }
        }

        $return_record['jiaoyijilu']  = $items;

        return $return_record;
    }
    /**
     * account number
     *
     * @return string
     */
    public function accountNumber()
    {
        $account = $this->account();

        return $account['zhanghu'] ? : '';
    }
    /**
     * check password
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    public static function check_password($username='', $password='')
    {
        $client = new Client([
            'verify'  => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            ],
        ]);

        try {
            $form = [
                'name'         => $username,
                'passwd'       => $password,
                'loginType'    => 2,
                'userType'     => 1,
                'rand'         => ' ',
                'imageField.x' => 32,
                'imageField.y' => 17,
                ];

            $response1 = $client->post('http://ecard.ecjtu.jx.cn/loginstudent.action', [
                'form_params' => $form,
            ]);
            
            $html = $response1->getBody()->getContents();
            if (preg_match('/pages\/top\.jsp.*mainFrame\.action/is', $html)) {
                return true;
            } else {
                return false;
            }

        } catch (\Exception $e) {
            return false;
        }

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

        $login = $LoginHandler->form([
            'name'         => $this->username,
            'passwd'       => $this->password,
            'loginType'    => 2,
            'userType'     => 1,
            'rand'         => ' ',
            'imageField.x' => 32,
            'imageField.y' => 17,
        ])->login($this->baseUrl . 'loginstudent.action');

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
