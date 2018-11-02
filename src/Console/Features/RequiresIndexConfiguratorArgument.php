<?php

namespace Larastic\Console\Features;

use InvalidArgumentException;
use Larastic\IndexConfigurator;
use Symfony\Component\Console\Input\InputArgument;

trait RequiresIndexConfiguratorArgument
{
    /**
     * @return IndexConfigurator
     */
    protected function getIndexConfigurator()
    {
        $configuratorClass = trim($this->argument('index-configurator'));

        $configuratorInstance = new $configuratorClass;

        if (!($configuratorInstance instanceof IndexConfigurator)) {
            throw new InvalidArgumentException(sprintf(
                'The class %s must extend %s.',
                $configuratorClass,
                IndexConfigurator::class
            ));
        }

        return (new $configuratorClass);
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [
            [
                'index-configurator',
                InputArgument::REQUIRED,
                'The index configurator class'
            ]
        ];
    }
}