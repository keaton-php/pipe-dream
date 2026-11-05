<?php

namespace Keaton\PipeDream\Tests\Fixtures;

/** Stands in for Laravel's URL generator inside a custom container. */
class FakeUrlGenerator
{
    public function route($name, $parameters = [])
    {
        return 'https://custom.test/'.$name.($parameters ? '?'.http_build_query($parameters) : '');
    }
}
