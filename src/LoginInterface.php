<?php
namespace Megoc\Ecjtu;

interface LoginInterface {
    /**
     * Get or set sent session_id
     *
     * @param string $session
     * @return void
     */
    public function session_id(string $session_id='');
    /**
     * Get username
     *
     * @return void
     */
    public function username(string $username='');
    /**
     * Get or set password
     *
     * @param string $password
     * @return void
     */
    public function password(string $password='');
    /**
     * Set form data
     *
     * @param array $form
     * @return void
     */
    public function form(array $form=[]);
    /**
     * set form submit action
     *
     * @param string $submit_url
     * @return void
     */
    public function formAction(string $submit_url);
    /**
     * login return success session_id
     *
     * @param string $submit_url
     * @return void
     */
    public function login(string $submit_url='');

    public function is_logined(bool $set_status = false);
    
}