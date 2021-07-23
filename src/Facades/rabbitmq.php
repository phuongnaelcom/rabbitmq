<?php

namespace phuongna\rabbitmq\Facades;

use Illuminate\Support\Facades\Facade;

class rabbitmq extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'rabbitmq';
    }
}
