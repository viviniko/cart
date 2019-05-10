<?php

namespace Viviniko\Cart\Listeners;

use Viviniko\Cart\CartStoreManager;
use Illuminate\Auth\Events\Login;

class CustomerEventSubscriber
{
    private $cartStoreManager;

    public function __construct(CartStoreManager $cartStoreManager)
    {
        $this->cartStoreManager = $cartStoreManager;
    }

    public function onLogin(Login $event)
    {
        $this->cartStoreManager->changeCurrentStore('authed_store') ;
	}

	/**
	 * Register the listeners for the subscriber.
	 *
	 * @param  \Illuminate\Events\Dispatcher  $events
	 */
	public function subscribe($events)
    {
		$events->listen(Login::class, 'Viviniko\Cart\Listeners\CustomerEventSubscriber@onLogin');
	}
}
