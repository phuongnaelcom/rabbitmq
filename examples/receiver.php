<?php
require_once __DIR__ . '/Connection/RabbitMQConnection.php';
use Connection\RabbitMQConnection;

$connection = new RabbitMQConnection(
    '127.0.0.1',
    5672,
    'admin',
    'admin'
);
$channel = $connection->channel();

$channel->queue_declare('task_queue', false, false, false, false);

echo " [*] Waiting for messages. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo ' [x] Received ', $msg->body, "\n";
    sleep(substr_count($msg->body, '.'));
    echo " [x] Done\n";
    $msg->ack();
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume('task_queue', '', false, false, false, false, $callback);

while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$connection->close();
