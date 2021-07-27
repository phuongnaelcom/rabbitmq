<?php

namespace phuongna\rabbitmq\Connectors;

use AMQPConnection;
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
        $this->connection = new AMQPConnection($config);

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
