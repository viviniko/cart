<?php

namespace Viviniko\Cart\Services;

use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

class Collection extends BaseCollection
{
    /**
     * Get subtotal.
     *
     * @return float
     */
    public function getSubtotal()
    {
        return $this->sum(function ($item) {
            return $item->subtotal;
        });
    }

    /**
     * Get grand total.
     *
     * @return float
     */
    public function getGrandTotal()
    {
        return $this->getSubtotal() - $this->getDiscountAmount() + $this->getShippingAmount();
    }

    /**
     * Get discount amount.
     *
     * @return float
     */
    public function getDiscountAmount()
    {
        return session('cart.coupon.discount', 0);
    }

    public function getDiscountCoupon()
    {
        return session('cart.coupon.code');
    }

    /**
     * Get shipping amount.
     *
     * @return float
     */
    public function getShippingAmount()
    {
        return session('cart.shipping.amount', 0);
    }

    /**
     * Get cart quantity.
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->sum(function ($item) {
            return $item->quantity;
        });
    }

    /**
     * Get cart total weight.
     *
     * @return float
     */
    public function getTotalWeight()
    {
        return $this->sum(function ($item) {
            return $item->gross_weight;
        });
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
            if (in_array($key, ['subtotal', 'grand_total', 'discount_amount', 'shipping_amount'])) {

            }
            return $result;
        }

        return parent::__get($key);
    }
}