<?php

namespace Viviniko\Cart\Events;

use Viviniko\Cart\Models\Cart;

class CartEvent
{
    /**
     * @var \Common\Cart\Models\Cart
     */
    public $cart;

    /**
     * CartEvent constructor.
     * @param \Common\Cart\Models\Cart $cart
     */
    public function __construct(Cart $cart = null)
    {
        $this->cart = $cart;
    }
}