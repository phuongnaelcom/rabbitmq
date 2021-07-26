<?php
namespace Connection;

require_once __DIR__ . '/../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConnection extends AMQPStreamConnection
{
}
