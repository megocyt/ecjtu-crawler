<?php

use PHPUnit\Framework\TestCase;
use Megoc\Ecjtu\Components\Portal;

class PortalTest extends TestCase
{
    /**
     * Undocumented variable
     *
     * @var Portal
     */
    protected $stack;

    public function setUp()
    {
        $this->stack = new Portal([
            'username' => 'your username',
            'password' => 'your password'
        ]);
    }

    public function testNotificaiton()
    {
        $notifications = $this->stack->notifications();

        $this->assertIsArray($notifications);

        $notification = array_pop($notifications);
        $this->assertCount(3, $notification);
        $notification_detail = $this->stack->notification_detail($notification['resource_id']);

        $this->assertIsArray($notification_detail);
        $this->assertArrayHasKey('content', $notificaiton_detail);
    }

    public function testLostNotification()
    {
        $notifications = $this->stack->lost_notifications();

        $this->assertIsArray($notifications);
    }
    public function testProfile()
    {
        $profile = $this->stack->profile();

        $this->assertIsArray($profile);
        $this->assertArrayHasKey('name', $profile);
        $this->assertArrayHasKey('sex', $profile);
        $this->assertIsInt($profile['sex']);
    }

}
