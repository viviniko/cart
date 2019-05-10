<?php

namespace Viviniko\Cart\Listeners;

use Viviniko\Cart\Events\CartStoreChanged;

class CartStoreChangedListener
{
    public function handle(CartStoreChanged $event)
    {
        $event->newCartStore->makeCart()->addAll($event->oldCartStore->getItems())->save();
    }
}