<?php


namespace phuongna\rabbitmq;

use Illuminate\Support\ServiceProvider;

use phuongna\rabbitmq\Console\WorkCommand;
use phuongna\rabbitmq\Console\ListenCommand;
use phuongna\rabbitmq\Console\RestartCommand;
use phuongna\rabbitmq\Connectors\RabbitMQConnector;
use phuongna\rabbitmq\Failed\NullFailedJobProvider;

/**
 * Class CustomQueueServiceProvider
 * @package phuongna\rabbitmq
 */
class CustomQueueServiceProvider extends ServiceProvider
{
    /**
     * Register the Config provider
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/rabbitmq.php' => config_path('rabbitmq.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerManager();

        $this->registerWorker();

        $this->registerListener();

        $this->registerFailedJobServices();
    }

    /**
     * Register the queue manager.
     * and also register the Queue Connection
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton('rabbitmq.queue', function ($app) {
            $manager = new CustomQueueManager($app);
            $this->registerConnectors($manager);
            return $manager;
        });

        $this->app->singleton('rabbitmq.queue.connection', function ($app) {
            return $app['rabbitmq.queue']->connection();
        });
    }

    /**
     * Register the queue worker,
     * also register Work Command,
     * and the restart Command..
     *
     * @return void
     */
    protected function registerWorker()
    {
        $this->registerWorkCommand();

        $this->registerRestartCommand();

        $this->app->singleton('rabbitmq.queue.worker', function ($app) {
            return new Worker($app['rabbitmq.queue'], $app['rabbitmq.queue.failer'], $app['events']);
        });
    }

    /**
     * Register the queue worker console command.
     *
     * @return void
     */
    protected function registerWorkCommand()
    {
        $this->app->singleton('command.rabbitmq.queue.work', function ($app) {
            return new WorkCommand($app['rabbitmq.queue.worker']);
        });

        $this->commands('command.rabbitmq.queue.work');
    }

    /**
     * Register the queue listener.
     *
     * @param  CustomQueueManager  $manager
     * @return void
     */
    protected function registerListener()
    {
        $this->registerListenCommand();

        $this->app->singleton('rabbitmq.queue.listener', function ($app) {
            return new Listener($app->basePath());
        });
    }

    /**
     * Register the queue listener console command.
     *
     * @return void
     */
    protected function registerListenCommand()
    {
        $this->app->singleton('command.rabbitmq.queue.listen', function ($app) {
            return new ListenCommand($app['rabbitmq.queue.listener']);
        });

        $this->commands('command.rabbitmq.queue.listen');
    }
    /**
     * Register the queue restart console command.
     *
     * @return void
     */
    public function registerRestartCommand()
    {
        $this->app->singleton('command.rabbitmq.queue.restart', function () {
            return new RestartCommand;
        });

        $this->commands('command.rabbitmq.queue.restart');
    }

    /**
     * Register the connectors on the queue manager.
     *
     * @param  CustomQueueManager  $manager
     * @return void
     */
    public function registerConnectors($manager)
    {
        foreach (['Rabbitmq'] as $connector) {
            $this->{"register{$connector}Connector"}($manager);
        }
    }

    /**
     * Register the Null queue connector.
     *
     * @param $manager
     * @return void
     */
    protected function registerRabbitmqConnector($manager)
    {
        $manager->addConnector('rabbitmq', function () {
            return new RabbitMQConnector;
        });
    }

    /**
     * Register the failed job services.
     *
     * @return void
     */
    protected function registerFailedJobServices()
    {
        $this->app->singleton('rabbitmq.queue.failer', function ($app) {
            $config = $app['config']['rabbitmq.failed'];

            // TODO: Add Database Failed Job Provider
            return new NullFailedJobProvider;
        });
    }
}
