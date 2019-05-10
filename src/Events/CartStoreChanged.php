<?php

namespace Viviniko\Cart\Events;

use Viviniko\Cart\Contracts\CartStore;

class CartStoreChanged
{
    /**
     * @var CartStore
     */
    public $oldCartStore;

    /**
     * @var CartStore
     */
    public $newCartStore;

    /**
     * CartStoreChanged constructor.
     * @param $oldCartStore
     * @param $newCartStore
     */
    public function __construct($oldCartStore, $newCartStore)
    {
        $this->oldCartStore = $oldCartStore;
        $this->newCartStore = $newCartStore;
    }
}