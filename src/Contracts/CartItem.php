<?php

namespace Viviniko\Cart\Contracts;

interface CartItem
{
    public function getSkuId();

    /**
     * @return \Viviniko\Currency\Money
     */
    public function getPrice();

    /**
     * @return float
     */
    public function getWeight();

    /**
     * @return float
     */
    public function getDiscount();

    /**
     * @return array
     */
    public function getOptions();
}