<?php

namespace Larastic\Facades;

use Illuminate\Support\Facades\Facade;

class ElasticClient extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'scout_elastic.client';
    }
}