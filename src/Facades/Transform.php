<?php

namespace Znck\Transform\Facades;

use Illuminate\Support\Facades\Facade;
use Znck\Transform\Transformer;

class Transform extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Transformer::class;
    }
}
