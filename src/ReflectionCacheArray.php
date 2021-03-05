<?php

namespace Amp\Injector;

class ReflectionCacheArray implements ReflectionCache
{
    private array $cache = [];


    /**
     * @return mixed
     */
    public function fetch(string $key)
    {
        return \array_key_exists($key, $this->cache) ? $this->cache[$key] : false;
    }

    /**
     * @param mixed $data
     */
    public function store(string $key, $data): void
    {
        $this->cache[$key] = $data;
    }
}
