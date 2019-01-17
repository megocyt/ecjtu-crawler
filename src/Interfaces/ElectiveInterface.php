<?php
namespace Megoc\Ecjtu\Interfaces;

/**
 * ElectiveInterface
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
interface ElectiveInterface
{
    /**
     * Course Query
     *
     * @param string $term
     * @return array
     */
    public function course(string $term = '');
    /**
     * Profile Query
     *
     * @return array
     */
    public function profile();
    /**
     * Public couser list
     *
     * @return array
     */
    public function public_course_list();
    /**
     * Get teacher's resume
     *
     * @param string $teacher_task_id
     * @return array
     */
    public function teacher_resume(string $teacher_task_id = '');
    /**
     * select state
     *
     * @return array
     */
    public function course_select_info();
    /**
     * cas authoriy
     *
     * @param string $uid
     * @param string $cas_link
     * @return void
     */
    public function cas_authority(string $uid, string $cas_link);

}