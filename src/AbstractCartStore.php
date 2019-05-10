<?php

namespace Viviniko\Cart;

use Illuminate\Support\Facades\Auth;
use Viviniko\Cart\Contracts\CartStore;
use Viviniko\Client\Facades\Client;

abstract class AbstractCartStore implements CartStore
{
    private $clientId;

    /**
     * @return string
     */
    public function getClientId()
    {
        if (empty($this->clientId)) {
            $this->clientId = Auth::check() ? Auth::id() : Client::id();
            $this->clientId = 'cart::' . $this->clientId;
        }

        return $this->clientId;
    }


    public function makeCart()
    {
        return new Cart($this->getClientId(), $this->getItems());
    }
}