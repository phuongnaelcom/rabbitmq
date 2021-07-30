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
    private static $corr_id;
    private static $response;

    /**
     * RabbitMQQueue constructor.
     * @param AMQPStreamConnection $amqpConnection
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
     * @param string $job
     * @param mixed $data
     * @param string $queue
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
     * @param string $payload
     * @param string $queue
     * @param array $options
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
     * @param \DateTime|int $delay
     * @param string $job
     * @param mixed $data
     * @param string $queue
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
        return $queue ?? null;
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
     * @param $_this
     * @param $name
     * @param $callback
     */
    public static function declareSubscribeServer($_this, $name, $callback)
    {
        $name = RabbitMQQueue::getQueueName($name);
        $_this->channel->queue_declare($name, 'fanout', false, false, false);
        $_this->channel->queue_bind($name);
        $_this->channel->basic_consume($name, '', false, true, false, false, $callback);
        while ($_this->channel->is_open()) {
            $_this->channel->wait();
        }
        self::close($_this);
    }

    /**
     * @param $_this
     * @param $name
     * @param $stringInput
     * @return void
     */
    public static function declarePublish($_this, $name, $stringInput)
    {
        $name = RabbitMQQueue::getQueueName($name);
        $_this->channel->exchange_declare($name, 'fanout', false, false, false);
        $msg = new AMQPMessage($stringInput);
        $_this->channel->basic_publish($msg, $name);
        return;
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
    }

    /**
     * @param $_this
     * @param $name
     * @param $stringInput
     * @return mixed
     */
    public static function declareRPCClient($_this, $name, $stringInput)
    {
        RabbitMQQueue::$corr_id = uniqid();
        $name = RabbitMQQueue::getQueueName($name);
        list($_this->callback_queue, ,) = $_this->channel->queue_declare(
            "",
            false,
            false,
            true,
            false
        );
        $_this->channel->basic_consume(
            $_this->callback_queue,
            '',
            false,
            true,
            false,
            false,
            array(
                $_this,
                'onResponse'
            )
        );
        $msg = new AMQPMessage(
            (string)$stringInput,
            array(
                'correlation_id' => RabbitMQQueue::$corr_id,
                'reply_to' => $_this->callback_queue
            )
        );
        $_this->channel->basic_publish($msg, '', $name);
        while (!RabbitMQQueue::$response) {
            try {
                $_this->channel->wait(null, false, 1);
            } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                $_this->channel->close();
                $_this->connection->close();
                return json_decode(json_encode([
                    'success' => false
                ]));
            }
        }
        return json_decode(RabbitMQQueue::$response);
    }

    /**
     * @param $response
     */
    public function onResponse($response)
    {
        if ($response->get('correlation_id') == RabbitMQQueue::$corr_id) {
            RabbitMQQueue::$response = $response->body;
        }
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
