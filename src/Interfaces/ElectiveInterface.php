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


}