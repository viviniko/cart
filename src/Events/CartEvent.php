<?php

namespace Viviniko\Cart\Events;

class CartEvent
{
    /**
     * @var Object
     */
    public $cart;

    /**
     * CartEvent constructor.
     * @param Object $cart
     */
    public function __construct($cart = null)
    {
        $this->cart = $cart;
    }
}