<?php

namespace Larastic\Console;

use Illuminate\Console\Command;
use Larastic\Console\Features\RequiresIndexConfiguratorArgument;
use Larastic\Facades\ElasticClient;
use Larastic\Payloads\IndexPayload;

class ElasticIndexDropCommand extends Command
{
    use RequiresIndexConfiguratorArgument;

    /**
     * @var string
     */
    protected $name = 'elastic:drop-index';

    /**
     * @var string
     */
    protected $description = 'Drop an Elasticsearch index';

    public function handle()
    {
        $configurator = $this->getIndexConfigurator();

        $payload = (new IndexPayload($configurator))
            ->get();

        ElasticClient::indices()
            ->delete($payload);

        $this->info(sprintf(
            'The index %s was deleted!',
            $configurator->getName()
        ));
    }
}