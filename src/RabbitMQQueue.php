<?php

namespace phuongna\rabbitmq;

use DateTime;

use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use phuongna\rabbitmq\Jobs\RabbitMQJob;

class RabbitMQQueue extends Queue implements QueueContract
{
    protected $connection;
    public $channel;
    protected $callback;

    protected $defaultQueue;
    protected $configQueue;

    /**
     * RabbitMQQueue constructor.
     * @param $amqpConnection
     * @param $config
     */
    public function __construct(AMQPStreamConnection $amqpConnection, $config)
    {
        $this->connection = $amqpConnection;
        $this->defaultQueue = $config['queue'];
        $this->configQueue = $config['queue_params'];
        $this->channel = $this->getChannel();
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string $job
     * @param  mixed  $data
     * @param  string $queue
     *
     * @return bool
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($data, $queue, []);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string $payload
     * @param  string $queue
     * @param  array  $options
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return true;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTime|int $delay
     * @param  string        $job
     * @param  mixed         $data
     * @param  string        $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, ['delay' => $delay]);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     *
     * @return \Illuminate\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        return null;
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    public static function getQueueName($queue)
    {
        return $queue??null;
    }

    /**
     * @return AMQPChannel
     */
    private function getChannel()
    {
        return $this->connection->channel();
    }

    /**
     * @param $_this
     * @param $name
     * @param $callback
     */
    public static function declareRPCServer($_this, $name, $callback)
    {
        $name = RabbitMQQueue::getQueueName($name);
        $_this->channel->queue_declare(
            $name,
            $_this->configQueue['passive'],
            $_this->configQueue['durable'],
            $_this->configQueue['exclusive'],
            $_this->configQueue['auto_delete']
        );
        $_this->channel->basic_qos(null, 1, null);
        $_this->channel->basic_consume($name, '',
            $_this->configQueue['passive'],
            $_this->configQueue['durable'],
            $_this->configQueue['exclusive'],
            $_this->configQueue['auto_delete'],
            $callback);
        while ($_this->channel->is_open()) {
            $_this->channel->wait();
        }
        self::close($_this);
    }

    /**
     * @param $request
     * @param string $string
     */
    public static function replyTo($request, $string = "[]")
    {
        $msg = new AMQPMessage(
            $string,
            array('correlation_id' => $request->get('correlation_id'))
        );
        $request->delivery_info['channel']->basic_publish(
            $msg,
            '',
            $request->get('reply_to')
        );
        $request->ack();
    }

    public function size($queue = null)
    {
        // TODO: Implement size() method.
    }

    public static function close($_this)
    {
        $_this->channel->close();
        $_this->close();
    }
}
