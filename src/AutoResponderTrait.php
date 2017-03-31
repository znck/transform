<?php namespace Znck\Transform;

trait AutoResponderTrait
{
    protected function responder(): Transformer
    {
        return resolve(Transformer::class);
    }

    protected function eagerLoading(): array
    {
        return $this->responder()->relations();
    }

    public function callAction($method, $parameters)
    {
        return $this->responder()->transform(parent::callAction($method, $parameters));
    }
}
