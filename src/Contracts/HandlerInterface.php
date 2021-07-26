<?php

namespace phuongna\rabbitmq\Contracts;

interface HandlerInterface
{
    /**
     * Handler Template that is called from the Custom Queue Worker
     *
     * @param  array  $data  Data from the Queue
     * @return void
     */
    public function handler(array $data = []);
}
