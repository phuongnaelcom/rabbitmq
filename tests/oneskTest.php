<?php

namespace phuongna\rabbitmq\Tests;

use phuongna\rabbitmq\Facades\rabbitmq;
use phuongna\rabbitmq\ServiceProvider;
use Orchestra\Testbench\TestCase;

class oneskTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'onesk' => rabbitmq::class,
        ];
    }

    public function testExample()
    {
        $this->assertEquals(1, 1);
    }
}
