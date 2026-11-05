<?php

namespace Tests;

use Orchestra\Testbench\TestCase;

class BaseTest extends TestCase 
{

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function it_can_do_something()
    {
        $this->assertTrue(true);
    }
}