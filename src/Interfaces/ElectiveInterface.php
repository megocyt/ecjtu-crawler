<?php
namespace Megoc\Ecjtu\Interfaces;

/**
 * ElectiveInterface
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
interface ElectiveInterface {
    /**
     * Course Query
     *
     * @param string $term
     * @return void
     */
    public function course(string $term='');
    /**
     * Profile Query
     *
     * @return void
     */
    public function profile();
    /**
     * Public couser list
     *
     * @return void
     */
    public function publicCourseList();
    /**
     * Get teacher's resume
     *
     * @param string $teacher_task_id
     * @return void
     */
    public function teacherResume(string $teacher_task_id='');
    /**
     * Get teacher's photo
     *
     * @param string $teacher_id
     * @param boolean $echo_string
     * @return void
     */
    public function teacherPhoto(string $teacher_id='', $echo_string=true);
    
}