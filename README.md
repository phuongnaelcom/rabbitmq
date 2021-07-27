# RabbitMQ

## Installation

Install via composer
```bash
composer require phuongna/rabbitmq
```

### Publish package assets

```bash
php artisan vendor:publish --provider="phuongna\rabbitmq\ServiceProvider"
```

## Security

If you discover any security related issues, please email
instead of using the issue tracker.

## How to use
####Server as service
```
$this->rabbit = app('rabbitmq.queue')->connection('rabbitmq');
RabbitMQQueue::declareRPCServer($this->rabbit, xxx, function ($request) {
    $this->rabbit->replyTo($request, dosomething($request));
});
```
####Client as service
```
$this->rabbit = app('rabbitmq.queue')->connection('rabbitmq');
$stringInput = json_encode(array);
RabbitMQQueue::declareRPCClient($this->rabbit, xxx, $stringInput);
```
