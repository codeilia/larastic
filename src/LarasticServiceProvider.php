<?php

namespace Larastic;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Elasticsearch\ClientBuilder;
use InvalidArgumentException;
use Larastic\Console\ElasticIndexCreateCommand;
use Larastic\Console\ElasticIndexDropCommand;
use Larastic\Console\ElasticIndexUpdateCommand;
use Larastic\Console\ElasticMigrateCommand;
use Larastic\Console\ElasticUpdateMappingCommand;
use Larastic\Console\IndexConfiguratorMakeCommand;
use Larastic\Console\SearchableModelMakeCommand;
use Laravel\Scout\EngineManager;
use Larastic\Console\SearchRuleMakeCommand;
use Illuminate\Http\Request;

class LarasticServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/scout_elastic.php' => config_path('scout_elastic.php'),
        ]);

        $this->commands([
            // make commands
            IndexConfiguratorMakeCommand::class,
            SearchableModelMakeCommand::class,
            SearchRuleMakeCommand::class,

            // elastic commands
            ElasticIndexCreateCommand::class,
            ElasticIndexUpdateCommand::class,
            ElasticIndexDropCommand::class,
            ElasticUpdateMappingCommand::class,
            ElasticMigrateCommand::class
        ]);

        $this
            ->app
            ->make(EngineManager::class)
            ->extend('elastic', function () {
                $indexerType = config('scout_elastic.indexer', 'single');
                $updateMapping = config('scout_elastic.update_mapping', true);

                $indexerClass = '\\Larastic\\Indexers\\'.ucfirst($indexerType).'Indexer';

                if (!class_exists($indexerClass)) {
                    throw new InvalidArgumentException(sprintf(
                        'The %s indexer doesn\'t exist.',
                        $indexerType
                    ));
                }

                return new ElasticEngine(new $indexerClass(), $updateMapping);
            });
    }

    public function register()
    {
        $this
            ->app
            ->singleton('scout_elastic.client', function() {
                $config = Config::get('scout_elastic.client');
                return ClientBuilder::fromConfig($config);
            });

        $this->app->singleton('RequestHandler', function ($app) {
            return new RequestHandler($app->make(Request::class));
        });
    }
}