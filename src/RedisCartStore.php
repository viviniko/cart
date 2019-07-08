<?php

namespace Viviniko\Cart;

use Illuminate\Support\Facades\Redis;

class RedisCartStore extends AbstractCartStore
{
    private $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param array $items
     * @param $ttl
     * @return void
     */
    public function setItems(array $items, $ttl)
    {
        $this->getDefaultRedisConnection()->setex(
            $this->getClientId(), $ttl, serialize($items)
        );
    }

    /**
     * @return array
     */
    public function getItems()
    {
        $items = @unserialize($this->getDefaultRedisConnection()->get($this->getClientId()));

        return empty($items) ? [] : $items;
    }

    /**
     * @return void
     */
    public function forget()
    {
        $this->getDefaultRedisConnection()->forget($this->getClientId());
    }

    private function getDefaultRedisConnection()
    {
        return Redis::connection($this->connection);
    }
}