<?php

namespace phuongna\rabbitmq\Connectors;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use phuongna\rabbitmq\RabbitMQQueue;

use Illuminate\Queue\Connectors\ConnectorInterface;

class RabbitMQConnector implements ConnectorInterface
{
    protected $connection;

    /**
     * Establish a queue connection.
     *
     * @param  array $config
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        // create connection with AMQP
        $this->connection = new AMQPStreamConnection($config['host'], $config['port'], $config['login'], $config['password'], $config['vhost']);

        return new RabbitMQQueue(
            $this->connection,
            $config
        );
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
