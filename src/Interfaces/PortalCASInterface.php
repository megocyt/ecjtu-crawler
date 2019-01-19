<?php
/*
 * @Author: Megoc
 * @Date: 2019-01-19 13:11:16
 * @Last Modified by: Megoc
 * @Last Modified time: 2019-01-19 13:12:45
 * @Email: megoc@megoc.org
 * @Description: Create by vscode
 */
namespace Megoc\Ecjtu\Interfaces;

interface PortalCASInterface
{
    /**
     * cas authority service
     *
     * @param string $service_cas_uri
     * @return string
     */
    public function cas_authority_link(string $service_cas_uri = '');
    /**
     * login system
     *
     * @param array $user
     * @return void
     */
    public function login(array $user = []);
    /**
     * encrpt password
     *
     * @param string $password
     * @return string
     */
    public function encrypted_password($password = '');
    /**
     * servie name to servie cas server url 
     *
     * @param string $service_name
     * @return string
     */
    public function service_name2service_uri($service_name = '');

}
