<?php
namespace Megoc\Ecjtu;

/**
 * Ecjtu Manager 
 * Author: Megoc <megoc@megoc.org>
 * Date: 2018/07/22
 */
class EcjtuManager
{
    /**
     * user information
     *
     * @var [type]
     */
    protected $user;
    /**
     * Ecard system
     *
     * @var [type]
     */
    public $Ecard;
    /**
     * Education manager system
     *
     * @var [type]
     */
    public $Education;
    /**
     * Elective manager system
     *
     * @var [type]
     */
    public $Elective;

    public function __construct($user = [])
    {
        $this->user = $user;

        $this->Ecard = new \Megoc\Ecjtu\Ecard([
            'username' => $this->user['username'],
            'password' => $this->user['ecard_password'],
        ]);
        $this->Education = new \Megoc\Ecjtu\Education([
            'username' => $this->user['username'],
            'password' => $this->user['jwxt_password'],
        ]);

        $this->Elective = new \Megoc\Ecjtu\Elective([
            'username' => $this->user['username'],
            'password' => $this->user['jwxt_password'],
        ]);

        // new Login();
    }
    
}
