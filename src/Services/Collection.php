<?php

namespace Viviniko\Cart\Services;

use Viviniko\Currency\Facades\Currency;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

class Collection extends BaseCollection
{
    /**
     * @var float
     */
    protected $_shipping_amount = 0;

    /**
     * @var string
     */
    protected $_coupon_code = null;

    /**
     * @var float
     */
    protected $_discount_amount = 0;

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
     * Set coupon.
     *
     * @param $coupon
     * @param $discountAmount
     * @return mixed
     * @throws \Exception
     */
    public function setCoupon($coupon, $discountAmount = 0)
    {
        if (empty($coupon)) {
            $this->_coupon_code = null;
            $this->_discount_amount = 0;
        } else {
            $this->_coupon_code = $coupon;
            $this->_discount_amount = $discountAmount;
        }
    }

    /**
     * Get discount amount.
     *
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->_discount_amount;
    }

    public function getDiscountCoupon()
    {
        return $this->_coupon_code;
    }

    public function setShippingAmount($shippingAmount)
    {
        $this->_shipping_amount = $shippingAmount;
    }

    /**
     * Get shipping amount.
     *
     * @return float
     */
    public function getShippingAmount()
    {
        return $this->_shipping_amount;
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
}