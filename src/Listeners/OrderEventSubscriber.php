<?php

namespace Viviniko\Cart\Listeners;

use Viviniko\Cart\Contracts\CartService;
use Viviniko\Sale\Events\OrderCreated;

class OrderEventSubscriber
{
	/**
	 * @var \Common\Cart\Contracts\CartService
	 */
	private $cartService;

	public function __construct(CartService $cartService)
    {
		$this->cartService = $cartService;
	}

	public function onOrderCreated(OrderCreated $event)
    {

	}

	/**
	 * Register the listeners for the subscriber.
	 *
	 * @param  \Illuminate\Events\Dispatcher  $events
	 */
	public function subscribe($events)
    {
		$events->listen(OrderCreated::class, 'Viviniko\Cart\Listeners\OrderEventSubscriber@onOrderCreated');
	}
}
