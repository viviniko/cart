<?php

namespace Viviniko\Cart;

use Illuminate\Support\Facades\Cookie;
use Viviniko\Client\Facades\Client;

class CookieCartStore extends AbstractCartStore
{
    /**
     * @return string
     */
    public function getClientId()
    {
        return 'ca_' . Client::id();
    }

    /**
     * @param array $items
     * @param $ttl
     * @return void
     */
    public function setItems(array $items, $ttl)
    {
        Cookie::queue(Cookie::make($this->getClientId(), serialize($items), $ttl));
    }

    /**
     * @return array
     */
    public function getItems()
    {
        $items = @unserialize(Cookie::get($this->getClientId()));

        return empty($items) ? [] : $items;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return count($this->getItems());
    }

    /**
     * @return void
     */
    public function forget()
    {
        Cookie::forget($this->getClientId());
    }
}