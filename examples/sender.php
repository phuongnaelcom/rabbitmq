<?php
require_once __DIR__ . '/Connection/RabbitMQConnection.php';
use Connection\RabbitMQConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new RabbitMQConnection(
    '127.0.0.1',
    5672,
    'admin',
    'admin'
);

$channel = $connection->channel();
$channel->queue_declare('task_queue', false, false, false, false);
$data = 'Hello World!';
$msg = new AMQPMessage($data, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
$channel->basic_publish($msg, '', 'task_queue');

echo " [x] Sent ".$data."\n";
$channel->close();
$connection->close();
