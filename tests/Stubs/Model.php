<?php

namespace Larastic\Tests\Stubs;

use Illuminate\Database\Eloquent\SoftDeletes;
use Larastic\Searchable;

class Model extends \Illuminate\Database\Eloquent\Model
{
    use Searchable, SoftDeletes;
}