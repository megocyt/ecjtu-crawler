<?php
namespace Megoc\Ecjtu\Interfaces;

/**
 * User login information
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
interface UserInterface {
    /**
     * get username
     *
     * @return void
     */
    public function username();
    /**
     * get password
     *
     * @return void
     */
    public function password();

}