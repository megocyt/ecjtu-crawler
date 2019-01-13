<?php
/* 
 * @Author: Megoc 
 * @Date: 2019-01-12 18:07:36 
 * @Last Modified by:   Megoc 
 * @Last Modified time: 2019-01-12 18:07:36 
 * @E-mail: megoc@megoc.org 
 * @Description: Create by vscode 
 */

namespace Megoc\Ecjtu\Interfaces;

interface LibraryInterface
{
    /**
     * history records
     *
     * @param integer $page
     * @return array
     */
    public function history($page = 1);
    /**
     * profile info
     *
     * @return array
     */
    public function profile();
    /**
     * cas authority login
     *
     * @param string $cas_link
     * @param string $uid
     * @return void
     */
    public function cas_authority($cas_link = '', $uid = '');

}