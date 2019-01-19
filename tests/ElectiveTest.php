<?php

use PHPUnit\Framework\TestCase;
use Megoc\Ecjtu\Components\Elective;

class ElectiveTest extends TestCase
{
    /**
     * stack
     *
     * @var Elective
     */
    protected $stack;

    public function setUp()
    {
        $this->stack = new Elective([
            'username' => 'your username',
            'password' => 'your password'
        ]);
    }

    public function testCourse()
    {
        $courses = $this->stack->course();

        $this->assertIsArray($courses);

        if (!empty($courses['lists'])) {
            $course = array_pop($courses['lists']);
            $this->assertIsArray($course);
            $this->assertIsString($course['teacher_task_id']);

            $resume = $this->stack->teacher_resume($course['teacher_task_id']);

            $this->assertIsArray($resume);

        }
    }

    public function testPublicCourseList()
    {
        $course_list = $this->stack->public_course_list();

        $this->assertIsArray($course_list);
    }

    public function testCourseSelectedInfo()
    {
        $info = $this->stack->course_select_info();

        $this->assertIsArray($info);
    }
}
