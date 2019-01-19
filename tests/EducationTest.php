<?php

use PHPUnit\Framework\TestCase;
use Megoc\Ecjtu\Components\Education;

class EducationTest extends TestCase
{
    /**
     * stack
     *
     * @var Education
     */
    protected $stack;

    public function setUp()
    {
        $this->stack = new Education([
            'username' => 'your username',
            'password' => 'your password'
        ]);
    }

    public function testScore()
    {
        $score = $this->stack->score();

        $this->assertIsArray($score);

        $this->assertIsArray(array_pop($score));
    }

    public function testCredit()
    {
        $credit = $this->stack->credit();

        $this->assertIsArray($credit);
        $this->assertArrayHasKey('name', $credit);
    }

    public function testSchedule()
    {
        $schedule = $this->stack->schedule();

        $this->assertIsArray($schedule);
        $this->assertCount(7, $schedule);
    }

    public function testWeekSchedule()
    {
        $week_schedule = $this->stack->week_schedule();

        $this->assertIsArray($week_schedule);
        $this->assertCount(7, $week_schedule);
    }

    public function testDaily()
    {
        $daily = $this->stack->daily();

        $this->assertIsArray($daily);
        $this->assertCount(5, $daily);
        $this->assertArrayHasKey('date', $daily);
        $this->assertArrayHasKey('week', $daily);
        $this->assertIsArray($daily['calendar_list']);
    }

    public function testExam()
    {
        $exam = $this->stack->exam();

        $this->assertIsArray($exam);

        $bexam = $this->stack->bexam();
        $this->assertIsArray($bexam);
    }

    public function testClassmate()
    {
        $classmates = $this->stack->classmate();

        $this->assertIsArray($classmates);

        $mate = array_pop($classmates);
        $this->assertIsArray($mate);
        $this->assertArrayHasKey('sex', $mate);
        $this->assertIsInt($mate['sex']);
    }

    public function testProfile()
    {
        $profile = $this->stack->profile();

        $this->assertIsArray($profile);
        $this->assertArrayHasKey('name', $profile);
        $this->assertArrayHasKey('sex', $profile);
        $this->assertIsInt($profile['sex']);
    }

    public function testClassNumber()
    {
        $class_number = $this->stack->class_number();

        $this->assertIsArray($class_number);
        $this->assertIsArray(array_pop($class_number));
    }

    public function testClassList()
    {
        $class_list = $this->stack->class_list('土木建筑学院', 2018);

        $this->assertIsArray($class_list);
    }

    public function testCollegeList()
    {
        $college_list = $this->stack->college_list();

        $this->assertIsArray($college_list);
        $this->assertIsString(array_pop($college_list));
    }

    public function testNotificaiton()
    {
        $notifications = $this->stack->notifications();

        $this->assertIsArray($notifications);

        $notification = array_pop($notifications);
        $this->assertCount(3, $notification);
        $notification_detail = $this->stack->notification_detail($notification['resource_id']);

        $this->assertIsArray($notification_detail);
        $this->assertArrayHasKey('content', $notification_detail);
    }
}
