<?php

use PHPUnit\Framework\TestCase;
use Megoc\Ecjtu\Components\Library;

class LibraryTest extends TestCase
{
    /**
     * stack
     *
     * @var Megoc\Ecjtu\Components\Library
     */
    protected $stack;

    public function setUp()
    {
        $this->stack = new Library([
            'username' => 'your username',
            'password' => 'your password'
        ]);
    }

    public function testHistory()
    {
        $history = $this->stack->history();

        $this->assertIsArray($history);
        $this->assertArrayHasKey('current_page', $history);
        $this->assertArrayHasKey('total_pages', $history);
        $this->assertArrayHasKey('lists', $history);
        $this->assertIsArray($history['lists']);
    }

    public function testProfile()
    {
        $profile = $this->stack->profile();

        $this->assertIsArray($profile);
        $this->assertCount(13, $profile);
        $this->assertArrayHasKey('name', $profile);
        $this->assertIsInt($profile['sex']);
    }
}
