<?php


namespace phuongna\rabbitmq;

use Illuminate\Support\ServiceProvider;

use phuongna\rabbitmq\Console\RestartCommand;
use phuongna\rabbitmq\Console\WorkCommand;
use phuongna\rabbitmq\Connectors\RabbitMQConnector;

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
    }

    /**
     * Register the queue manager.
     * and also register the Queue Connection
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton('custom.queue', function ($app) {
            $manager = new CustomQueueManager($app);
            $this->registerConnectors($manager);
            return $manager;
        });

        $this->app->singleton('custom.queue.connection', function ($app) {
            return $app['custom.queue']->connection();
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

        $this->app->singleton('custom.queue.worker', function ($app) {
            return new Worker($app['custom.queue'], $app['custom.queue.failer'], $app['events']);
        });
    }

    /**
     * Register the queue worker console command.
     *
     * @return void
     */
    protected function registerWorkCommand()
    {
        $this->app->singleton('command.custom.queue.work', function ($app) {
            return new WorkCommand($app['custom.queue.worker']);
        });

        $this->commands('command.custom.queue.work');
    }

    /**
     * Register the connectors on the queue manager.
     *
     * @param  phuongna\rabbitmq\CustomQueueManager  $manager
     * @return void
     */
    public function registerConnectors($manager)
    {
        foreach (['Rabbitmq'] as $connector) {
            $this->{"register{$connector}Connector"}($manager);
        }
    }
    /**
     * Register the queue restart console command.
     *
     * @return void
     */
    public function registerRestartCommand()
    {
        $this->app->singleton('command.custom.queue.restart', function () {
            return new RestartCommand;
        });

        $this->commands('command.custom.queue.restart');
    }


    /**
     * Register the Null queue connector.
     *
     * @param  phuongna\rabbitmq\CustomQueueManager  $manager
     * @return void
     */
    protected function registerRabbitmqConnector($manager)
    {
        $manager->addConnector('rabbitmq', function () {
            return new RabbitMQConnector;
        });
    }

}
