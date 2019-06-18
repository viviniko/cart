<?php

namespace Viviniko\Cart;

use Illuminate\Support\Str;
use Viviniko\Cart\Contracts\CartItem;

class Item implements CartItem
{
    /**
     * @var string
     */
    private $skuId;

    /**
     * @var \Viviniko\Currency\Amount
     */
    private $price;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var float
     */
    private $weight;

    /**
     * @var float
     */
    private $discount;

    /**
     * @var array
     */
    private $specs;

    public function __construct(CartItem $item)
    {
        $this->skuId = $item->getSkuId();
        $this->price = $item->getPrice();
        $this->quantity = $item->getQuantity();
        $this->weight = $item->getWeight();
        $this->discount = $item->getDiscount();
        $this->specs = $item->getSpecs();
    }

    public function equals(CartItem $cartItem)
    {
        return $this->skuId == $cartItem->getSkuId();
    }

    public function plus(CartItem $cartItem)
    {
        if ($this->equals($cartItem)) {
            $this->quantity += $cartItem->getQuantity();

            return $this->quantity;
        }

        return false;
    }

    public function getSubtotal()
    {
        return $this->getPrice()->discount($this->getDiscount())->mul($this->getQuantity());
    }

    public function getTotalWeight()
    {
        return $this->getQuantity() * $this->getWeight();
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function getDiscount()
    {
        return $this->discount;
    }

    public function getSkuId()
    {
        return $this->skuId;
    }

    /**
     * @return array
     */
    public function getSpecs()
    {
        return $this->specs;
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
}