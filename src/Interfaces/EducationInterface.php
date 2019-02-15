<?php
namespace Megoc\Ecjtu\Interfaces;

/**
 * EducationInterface
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
interface EducationInterface
{
    /**
     * 成绩
     *
     * @param string $term
     * @return array
     */
    public function score(string $term = '');
    /**
     * 学分获得情况
     *
     * @return array
     */
    public function credit();
    /**
     * 第二课堂学分获得情况
     *
     * @return array
     */
    public function second_credit();
    /**
     * 课表
     *
     * @param string $term
     * @return array
     */
    public function schedule(string $term = '');
    /**
     * 周历
     *
     * @param string $week
     * @param string $term
     * @return array
     */
    public function week_schedule(string $week = '', string $term = '');
    /**
     * 日历
     *
     * @param string $date
     * @return array
     */
    public function daily(string $date);
    /**
     * 考试安排
     *
     * @param string $term
     * @return array
     */
    public function exam(string $term = '');
    /**
     * 补考安排
     *
     * @param string $term
     * @return array
     */
    public function bexam(string $term = '');
    /**
     * 实验安排
     *
     * @param string $term
     * @return array
     */
    public function experiment(string $term = '');
    /**
     * 班级名单
     *
     * @param string $class_id
     * @return array
     */
    public function classmate(string $class_id = '');
    /**
     * 账户信息
     *
     * @return array
     */
    public function profile();
    /**
     * 小班序号
     *
     * @param string $term
     * @return array
     */
    public function class_number(string $term = '');
    /**
     * 班级列表
     *
     * @param string $college
     * @param string $grade
     * @return array
     */
    public function class_list(string $college = '', string $grade = '');
    /**
     * 学院列表
     *
     * @return array
     */
    public function college_list();
    /**
     * cas认证登录
     *
     * @param string $uid
     * @param string $cas_link
     * @return void
     */
    public function cas_authority(string $uid, string $cas_link = '');
    /**
     * 登录系统
     *
     * @param array $user
     * @return void
     */
    public function login(array $user = []);
    /**
     * 通知公告
     *
     * @param integer $page
     * @return array
     */
    public function notifications(int $page = 1);
    /**
     * 通知公告信息
     *
     * @param string $resource_id
     * @return array
     */
    public function notification_detail(string $resource_id = '');

}