<?php

namespace Amp\Injector;

interface ReflectionCache
{
    /**
     * @return mixed
     */
    public function fetch(string $key);

    /**
     * @param mixed $data
     */
    public function store(string $key, $data): void;
}
