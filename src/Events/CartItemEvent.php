<?php

namespace Viviniko\Cart\Events;

use Viviniko\Cart\Cart;
use Viviniko\Cart\Item;

class CartItemEvent
{
    /**
     * @var Object
     */
    public $cart;

    public $item;

    /**
     * CartEvent constructor.
     * @param Cart $cart
     * @param Item $item
     */
    public function __construct($cart, $item)
    {
        $this->cart = $cart;
        $this->item = $item;
    }
}