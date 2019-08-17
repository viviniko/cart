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
     * @var \Viviniko\Currency\Money
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
    private $options;

    /**
     * @var mixed
     */
    private $source;

    /**
     * Source resolver
     *
     * @var mixed
     */
    protected static $sourceResolvers;

    public function __construct(CartItem $item)
    {
        $this->skuId = $item->getSkuId();
        $this->price = $item->getPrice();
        $this->quantity = 1;
        $this->weight = $item->getWeight();
        $this->discount = $item->getDiscount();
        $this->options = $item->getOptions();
    }

    public function equals(CartItem $cartItem)
    {
        return $this->skuId == $cartItem->getSkuId();
    }

    public function plus(CartItem $cartItem, $quantity = 1, $setQuantity = false)
    {
        if ($this->equals($cartItem)) {
            $this->quantity = $setQuantity ? $quantity : ($this->quantity + $quantity);

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
    public function getOptions()
    {
        return $this->options;
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

        if ($this->resolveSource() !== false) {
            return data_get($this->resolveSource(), $key);
        }

        throw new \BadMethodCallException();
    }

    public static function configSourceResolvers($resolvers)
    {
        self::$sourceResolvers = $resolvers;
    }

    public function resolveSource() {
        if (!$this->source) {
            foreach(self::$sourceResolvers as $resolver) {
                if (($source = $resolver($this)) !== false) {
                    $this->source = $source;
                    break;
                }
            }
        }

        return $this->source;
    }
}