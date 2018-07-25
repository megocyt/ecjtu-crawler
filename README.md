
Ecjtu Crawler 是帮助开发者获取Ecjtu教务等信息，包含（教务、选课、一卡通等）

- 简单的获取信息接口
  score,daily,classmate,schedule,exam,experiment,trade,trades,course...

```php
$EcjtuManager = new \Megoc\Ecjtu\EcjtuManager([
    'username'       => 'student_id',
    'jwxt_password'  => 'educative password',
    'ecard_password' => 'ecard password'
]);

// 一卡通当日消费
$trade = $EcjtuManager->Ecard->trade();
var_dump($trade);
// 获取2018.1学期的成绩
$score = $EcjtuManager->Education->score('2018.1');
var_dump($score);
// 获取2018.1的选课
$course = $EcjtuManager->Elective->course('2018.1');
var_dump($course);


## Installing Ecjtu Crawler

The recommended way to install Ecjtu Crawler is through
[Composer](http://getcomposer.org).

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
```

Next, run the Composer command to install the latest stable version of Ecjtu Crawler:

```bash
php composer.phar require megoc/ecjtu
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

You can then later update Ecjtu Crawler using composer:

 ```bash
composer.phar update
 ```
