<?php

namespace Test\Znck\Transform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Test\Znck\Transform\TestDoubles\FooModel;
use Znck\Transform\Transformer;

class TransformerTest extends TestCase
{
    protected function transformer($scheme)
    {
        $mock = $this->createMock(Request::class);

        $mock->expects($this->once())->method('query')->willReturn($scheme);

        return new Transformer($mock);
    }

    public function test_it_can_transform_a_model()
    {
        $transformer = $this->transformer(['foo' => ['bar']]);
        $model = new FooModel(['bar' => 1]);

        $this->assertEquals(['foo' => ['bar' => 1]], $transformer->transformModel($model));
    }
}
