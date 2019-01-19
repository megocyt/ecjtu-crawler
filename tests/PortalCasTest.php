<?php

use PHPUnit\Framework\TestCase;
use Megoc\Ecjtu\Components\PortalCAS;

class PortalCasTest extends TestCase
{
    /**
     * stack
     *
     * @var PortalCAS
     */
    protected $stack;

    public function setUp()
    {
        $this->stack = new PortalCAS([
            'username' => 'your username',
            'password' => 'your password'
        ]);
    }

    public function testCasAuthority()
    {
        $service_cas_uri = $this->stack->service_name2service_uri('portal');
        $this->assertIsString($service_cas_uri);

        $cas_link = $this->stack->cas_authority_link($service_cas_uri);
        $this->assertIsString($cas_link);
    }

    public function testEncPassowrd()
    {
        $enc_password = $this->stack->encrypted_password('123456');

        $this->assertIsString($enc_password);
    }
}
