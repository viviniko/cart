<?php

namespace Viviniko\Cart;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Viviniko\Cart\Contracts\CartItem;
use Viviniko\Cart\Contracts\CartStore;
use Viviniko\Cart\Events\CartItemAdded;
use Viviniko\Cart\Events\CartItemEvent;
use Viviniko\Cart\Events\CartItemRemoved;
use Viviniko\Cart\Events\CartItemUpdated;
use Viviniko\Currency\Money;

class Cart
{
    use Macroable;

    /**
     * @var array
     */
    protected $items;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var \Viviniko\Cart\Contracts\CartStore
     */
    protected $cartStore;

    private function __construct(array $items = [], array $data = [])
    {
        $this->items = $items;
        $this->data = $data;
    }

    public static function create($cartStore)
    {
        if ($cartStore instanceof CartStore) {
            $cart = new static();
            $cart->setCartStore($cartStore);

            return $cart;
        } else if (is_array($cartStore)) {
            return new static($cartStore);
        }

        throw new \InvalidArgumentException();
    }

    public function add(CartItem $cartItem, $quantity = 1)
    {
        foreach ($this->items as $item) {
            if ($item->equals($cartItem)) {
                $item->plusQuantity($quantity);
                $this->event(new CartItemUpdated($this, $item));
                return $this;
            }
        }

        $item = new Item($cartItem, $quantity);
        $this->items[] = $item;
        $this->event(new CartItemAdded($this, $item));

        return $this;
    }

    public function put(CartItem $cartItem, $quantity)
    {
        if ($quantity > 0) {
            foreach ($this->items as $item) {
                if ($item->equals($cartItem)) {
                    $item->setQuantity($quantity);
                    $this->event(new CartItemUpdated($this, $item));
                    return $this;
                }
            }
            return $this->add($cartItem, $quantity);
        }

        return $this->remove($cartItem);
    }

    public function remove(CartItem $cartItem)
    {
        $this->items = array_filter($this->items, function (Item $item) use ($cartItem, &$removedItem) {
            if ($item->equals($cartItem)) {
                $removedItem = $item;
                return false;
            }
            return true;
        });
        if ($removedItem)
            $this->event(new CartItemRemoved($this, $removedItem));
        return $this;
    }

    public function addAll(array $cartItems)
    {
        $notFoundItems = [];
        foreach ($this->items as $item) {
            foreach ($cartItems as $cartItem) {
                if ($item->plus($cartItem) === false) {
                    $notFoundItems[] = $cartItem;
                }
            }

        }

        $this->items = array_merge($this->items, $notFoundItems);

        return $this;
    }

    public function save()
    {
        $this->getCartStore()->setItems($this->items, Config::get('cart.ttl', 15 * 24 * 60));
    }

    /**
     * Get subtotal.
     *
     * @return Money
     */
    public function getSubtotal()
    {
        return array_reduce($this->items, function ($money, $item) {
            return $money->add($item->subtotal);
        }, Money::create(0));
    }

    /**
     * Get cart quantity.
     *
     * @return int
     */
    public function getQuantity()
    {
        return array_reduce($this->items, function ($result, $item) {
            $result += $item->quantity;
            return $result;
        }, 0);
    }

    /**
     * Get cart total weight.
     *
     * @return float
     */
    public function getTotalWeight()
    {
        return array_reduce($this->items, function ($result, $item) {
            $result += $item->weight;
            return $result;
        }, 0);
    }

    /**
     * Get cart items.
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * Dynamically set an attribute on the cart.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Dynamically access collection proxies.
     *
     * @param  string  $key
     * @return mixed
     *
     * @throws \Exception
     */
    public function __get($key)
    {
        if (method_exists($this, $method = 'get' . Str::studly($key))) {
            $result = $this->$method();

            return $result;
        }

        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        throw new \BadMethodCallException();
    }

    public function __sleep()
    {
        return ['items', 'data'];
    }

    /**
     * Get cart statistics.
     *
     * @return mixed
     */
    public function getStatistics()
    {
        return collect($this->data)->merge(collect(['quantity', 'subtotal'])->mapWithKeys(function ($item) {
            return [$item => $this->$item];
        }));
    }

    public function setCartStore(CartStore $cartStore)
    {
        $this->cartStore = $cartStore;
        $this->items = $cartStore->getItems();

        return $this;
    }

    public function getCartStore()
    {
        return $this->cartStore;
    }

    private function event(CartItemEvent $cartItemEvent)
    {
        event($cartItemEvent);
    }
}