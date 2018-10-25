<?php

namespace Viviniko\Cart;

use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use Viviniko\Currency\Amount;
use Viviniko\Currency\Facades\Currency;

class Collection extends BaseCollection
{
    /**
     * @var Amount
     */
    protected $_shipping_amount;

    /**
     * @var string
     */
    protected $_coupon_code = null;

    /**
     * @var Amount
     */
    protected $_discount_amount = 0;

    public function __construct($items = [])
    {
        parent::__construct($items);
        $this->_shipping_amount = Currency::createBaseAmount(0);
        $this->_discount_amount = Currency::createBaseAmount(0);
    }

    /**
     * Get subtotal.
     *
     * @return float
     */
    public function getSubtotal()
    {
        return $this->reduce(function ($amount, $item) {
            return $amount ? $amount->add($item->subtotal) : $item->subtotal;
        }, null);
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
            $this->_coupon_code = null;
            $this->_discount_amount = Currency::createBaseAmount(0);
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