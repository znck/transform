<?php

namespace Znck\Transform;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class TransformServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton(Transformer::class, function () {
            return new Transformer($this->app->make(Request::class));
        });
    }

    public function provides()
    {
        return [Transformer::class];
    }
}
