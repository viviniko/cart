<?php

namespace Viviniko\Cart\Services;

interface CartService
{
    /**
     * Add Item.
     *
     * @param  int  $itemId
     * @param  int  $quantity
     *
     * @return mixed
     */
    public function add($itemId, $quantity);

    /**
     * Remove from cart.
     *
     * @param $cartId
     *
     * @return int
     */
    public function remove($cartId);

    /**
     * Set coupon.
     *
     * @param $coupon
     * @return mixed
     * @throws \Common\Promotion\Exceptions\PromotionException
     */
    public function setCoupon($coupon);

    /**
     * Set cart item quantity.
     *
     * @param $cartId
     * @param $quantity
     * @return mixed
     */
    public function setItemQuantity($cartId, $quantity);

    /**
     * Get cart items.
     *
     * @param null $clientId
     * @return \Common\Cart\Services\Collection
     */
    public function getItems($clientId = null);

    /**
     * Get cart quantity count.
     *
     * @param null $clientId
     * @return int
     */
    public function getQuantity($clientId = null);

    /**
     * Get cart statistics.
     *
     * @param null $clientId
     * @return mixed
     */
    public function getStatistics($clientId = null);

    /**
     * Sync customer client id.
     *
     * @param $customerId
     * @param null $clientId
     * @return mixed
     */
    public function syncCustomerClientId($customerId, $clientId = null);
}