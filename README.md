
Ecjtu Crawler 快速获取教务信息

- 简单的获取信息接口
  score,daily,classmate,schedule,exam,experiment,trade,trades,course...

```php
$EcjtuManager = new \Megoc\Ecjtu\EcjtuManager([
    'username'       => 'student_id',
    'jwxt_password'  => 'education password',
]);

// 获取2018.1学期的成绩
$score = $EcjtuManager->getEducation()->score('2018.1');
var_dump($score);
// 获取2018.1的选课
$course = $EcjtuManager->getElective()->course('2018.1');
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
