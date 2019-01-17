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
     * Score query
     *
     * @param string $term
     * @return array
     */
    public function score(string $term = '');
    /**
     * Credit Query
     *
     * @param string $term
     * @return array
     */
    public function credit();
    /**
     * Schedule Query
     *
     * @param string $term
     * @return array
     */
    public function schedule(string $term = '');
    /**
     * week schedule
     *
     * @param string $week
     * @param string $term
     * @return array
     */
    public function week_schedule(string $week = '', string $term = '');
    /**
     * Daily schedule Query
     *
     * @param string $date
     * @return array
     */
    public function daily(string $date);
    /**
     * Exam arrange Query
     *
     * @param string $term
     * @return array
     */
    public function exam(string $term = '');
    /**
     * Bexam arrange Query
     *
     * @param string $term
     * @return array
     */
    public function bexam(string $term = '');
    /**
     * Experiment Query
     *
     * @param string $term
     * @return array
     */
    public function experiment(string $term = '');
    /**
     * Classmate Query
     *
     * @param string $class_id
     * @return array
     */
    public function classmate(string $class_id = '');
    /**
     * Profile
     *
     * @return array
     */
    public function profile();
    /**
     * Class Number Query
     *
     * @param string $term
     * @return array
     */
    public function class_number(string $term = '');
    /**
     * class list
     *
     * @param string $major
     * @param string $grade
     * @return array
     */
    public function class_list(string $major = '', string $grade = '');
    /**
     * college list
     *
     * @return array
     */
    public function college_list();
    /**
     * cas authority
     *
     * @param string $uid
     * @param string $cas_link
     * @return void
     */
    public function cas_authority(string $uid, string $cas_link = '');
    /**
     * login
     *
     * @param array $user
     * @return void
     */
    public function login(array $user = []);

}