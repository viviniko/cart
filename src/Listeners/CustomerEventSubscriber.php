<?php

namespace Viviniko\Cart\Listeners;

use Viviniko\Cart\Contracts\CartService;
use Illuminate\Auth\Events\Login;

class CustomerEventSubscriber
{
	/**
	 * @var \Viviniko\Cart\Contracts\CartService
	 */
	private $cartService;

	public function __construct(CartService $cartService)
    {
		$this->cartService = $cartService;
	}

	public function onLogin(Login $event)
    {
        $this->cartService->syncCustomerClientId($event->user->id);
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
