<?php

namespace Viviniko\Cart\Contracts;

use Viviniko\Cart\Cart;

interface CartStore
{
    /**
     * @return string
     */
    public function getClientId();

    /**
     * @param array $items
     * @param $ttl
     * @return void
     */
    public function setItems(array $items, $ttl);

    /**
     * @return array
     */
    public function getItems();

    /**
     * @return void
     */
    public function forget();

    /**
     * @return Cart
     */
    public function makeCart();

}