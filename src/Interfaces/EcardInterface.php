<?php
namespace Megoc\Ecjtu\Interfaces;

/**
 * Ecard Interface
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
interface EcardInterface{
    /**
     * Account
     *
     * @return array
     */
    public function account();
    /**
     * Get user photo
     * $echo_string if is true, this function return a string that encode by base64_encode, or return iamge stream
     *
     * @param boolean $echo_string
     * @return string or bit
     */
    public function photo(bool $echo_string=true);
    /**
     * Get account number
     *
     * @return string
     */
    public function accountNumber();

    /**
     * Today's trade 
     *
     * @return array
     */
    public function trade();
    /**
     * History trades
     * $start_date can be date string like 2018-01-01 or a number that started before number's day ago
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function trades(string $start_date='', string $end_date='');
}
