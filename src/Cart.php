<?php

namespace Viviniko\Cart;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Viviniko\Cart\Contracts\CartItem;
use Viviniko\Cart\Contracts\CartStore;
use Viviniko\Cart\Events\CartItemAdded;
use Viviniko\Cart\Events\CartItemEvent;
use Viviniko\Cart\Events\CartItemRemoved;
use Viviniko\Cart\Events\CartItemUpdated;
use Viviniko\Currency\Facades\Currency;

class Cart
{
    /**
     * @var \Viviniko\Currency\Amount
     */
    protected $shippingAmount;

    /**
     * @var string
     */
    protected $couponCode = null;

    /**
     * @var \Viviniko\Currency\Amount
     */
    protected $discountAmount = 0;

    /**
     * @var \Viviniko\Cart\Contracts\CartStore
     */
    protected $cartStore;

    /**
     * @var array
     */
    protected $items;

    /**
     * @var \Closure
     */
    protected $fromResolver;

    /**
     * @var array
     */
    protected $froms;

    public function __construct(CartStore $cartStore)
    {
        $this->setCartStore($cartStore);
        $this->shippingAmount = Currency::createBaseAmount(0);
        $this->discountAmount = Currency::createBaseAmount(0);
    }

    public function add(CartItem $cartItem, $quantity = 1, $setQuantity = false)
    {
        foreach ($this->items as $item) {
            if ($item->plus($cartItem, $quantity, $setQuantity) !== false) {
                $this->event(new CartItemUpdated($this, $item));
                return $this;
            }
        }

        $item = new Item($cartItem);
        $this->items[] = $item;
        $this->event(new CartItemAdded($this, $item));

        return $this;
    }

    public function remove(CartItem $cartItem)
    {
        $this->items = array_filter($this->items, function (Item $item) use ($cartItem, &$removedItem) {
            if ($item->equals($cartItem)) {
                $this->event(new CartItemRemoved($this, $item));
                return false;
            }
        });

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
     * @return float
     */
    public function getSubtotal()
    {
        return array_reduce($this->items, function ($amount, $item) {
            return $amount->add($item->subtotal);
        }, Currency::createBaseAmount(0));
    }

    /**
     * Get grand total.
     *
     * @return float
     */
    public function getGrandTotal()
    {
        return $this->getSubtotal()->sub($this->getDiscountAmount())->add($this->getShippingAmount());
    }

    /**
     * Set coupon.
     *
     * @param $coupon
     * @param $discountAmount
     * @return mixed
     * @throws \Exception
     */
    public function setCoupon($coupon, $discountAmount = null)
    {
        if (empty($coupon)) {
            $this->couponCode = null;
            $this->discountAmount = Currency::createBaseAmount(0);
        } else {
            $this->couponCode = $coupon;
            $this->discountAmount = $discountAmount;
        }

        return $this;
    }

    /**
     * Get discount amount.
     *
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->discountAmount;
    }

    public function getDiscountCoupon()
    {
        return $this->couponCode;
    }

    public function setShippingAmount($shippingAmount)
    {
        $this->shippingAmount = $shippingAmount;

        return $this;
    }

    /**
     * Get shipping amount.
     *
     * @return float
     */
    public function getShippingAmount()
    {
        return $this->shippingAmount;
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

        throw new \BadMethodCallException();
    }

    /**
     * Get cart statistics.
     *
     * @return mixed
     */
    public function getStatistics()
    {
        return collect(['quantity', 'subtotal', 'grand_total', 'discount_amount', 'shipping_amount'])->mapWithKeys(function ($item) {
            return [$item => $this->$item];
        });
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

    public function from(CartItem $cartItem, $property = null)
    {
        $from = isset($this->froms[$cartItem->getSkuId()])
            ? $this->froms[$cartItem->getSkuId()]
            : ($this->froms[$cartItem->getSkuId()] = ($this->fromResolver)($cartItem));

        return $property ? $from->$property : $from;
    }

    public function setFromResolver(\Closure $closure)
    {
        $this->fromResolver = $closure;

        return $this;
    }

    private function event(CartItemEvent $cartItemEvent)
    {
        event($cartItemEvent);
    }
}