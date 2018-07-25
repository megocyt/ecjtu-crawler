<?php
namespace Megoc\Ecjtu\Interfaces;

/**
 * EducationInterface
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
interface EducationInterface {
    /**
     * Score query
     *
     * @param string $term
     * @return void
     */
    public function score(string $term='');
    /**
     * Credit Query
     *
     * @param string $term
     * @return void
     */
    public function credit();
    /**
     * Schedule Query
     *
     * @param string $term
     * @return void
     */
    public function schedule(string $term='');
    /**
     * Daily schedule Query
     *
     * @param string $date
     * @return void
     */
    public function daily(string $date);
    /**
     * Exam arrange Query
     *
     * @param string $term
     * @return void
     */
    public function exam(string $term='');
    /**
     * Bexam arrange Query
     *
     * @param string $term
     * @return void
     */
    public function bexam(string $term='');
    public function empty_classroom(string $term='');
    /**
     * Experiment Query
     *
     * @param string $term
     * @return void
     */
    public function experiment(string $term='');
    /**
     * Classmate Query
     *
     * @param string $class_id
     * @return void
     */
    public function classmate(string $class_id='');
    /**
     * Class Number Query
     *
     * @param string $term
     * @return void
     */
    public function class_number(string $term='');
    /**
     * Profile
     *
     * @return void
     */
    public function profile();

}