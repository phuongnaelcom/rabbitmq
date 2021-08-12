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
        $_this->channel->queue_declare($name, false, false, false, false);
        $_this->channel->basic_qos(null, 1, null);
        $_this->channel->basic_consume($name, '', false, false, false, false, $callback);
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
        $_this->channel->exchange_declare($name, 'fanout', false, false, false);
        list($queue_name, ,) = $_this->channel->queue_declare("", false, false, false, false);
        $_this->channel->queue_bind($queue_name, $name);
        $_this->channel->basic_consume($queue_name, '', false, false, false, false, $callback);
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
        try {
            $_this->channel->exchange_declare($name, 'fanout', false, false, false);
            $msg = new AMQPMessage($stringInput);
            $_this->channel->basic_publish($msg, $name);
            self::close($_this);
        } catch (\Exception $e){
            // TODO: nothing
        }
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
        try {
            list($_this->callback_queue, ,) = $_this->channel->queue_declare("", false, false, true, false );
            $_this->channel->basic_consume( $_this->callback_queue, '', false, false, false, false,
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
                    $_this->channel->wait(null, false, 3);
                } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                    self::close($_this);
                    return json_decode(json_encode([
                        'success' => false
                    ]));
                }
            }
            return json_decode(RabbitMQQueue::$response);
        } catch (\Exception $e){
            // TODO: nothing
        }
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

    public static function close($_this=null)
    {
        if($_this != null) {
            $_this->channel->close();
            $_this->close();
        }
    }
}
