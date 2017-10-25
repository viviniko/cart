<?php

namespace Viviniko\Cart\Services\Cart;

use Viviniko\Agent\Facades\Agent;
use Viviniko\Cart\Events\CartCreated;
use Viviniko\Cart\Events\CartRemoved;
use Viviniko\Cart\Events\CartUpdated;
use Viviniko\Cart\Services\Collection;
use Viviniko\Catalog\Contracts\AttributeService;
use Viviniko\Catalog\Contracts\ProductService;
use Viviniko\Promotion\Contracts\PromotionService;
use Viviniko\Repository\SimpleRepository;
use Viviniko\Cart\Contracts\CartService as CartServiceInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;

class EloquentCart extends SimpleRepository implements CartServiceInterface
{
    protected $modelConfigKey = 'cart.cart';

    /**
     * @var \Viviniko\Catalog\Contracts\ProductService
     */
    protected $productService;

    /**
     * @var \Viviniko\Catalog\Contracts\AttributeService
     */
    protected $attributeService;

    /**
     * @var \Viviniko\Promotion\Contracts\PromotionService
     */
    protected $promotionService;

    /**
     * Instance of the session manager.
     *
     * @var \Illuminate\Session\SessionManager
     */
    protected $session;

    /**
     * Instance of the event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * EloquentCart constructor.
     * @param \Viviniko\Catalog\Contracts\ProductService $productService
     * @param \Viviniko\Catalog\Contracts\AttributeService $attributeService
     * @param \Viviniko\Promotion\Contracts\PromotionService $promotionService
     * @param \Illuminate\Session\SessionManager $session
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(
        ProductService $productService,
        AttributeService $attributeService,
        PromotionService $promotionService,
        SessionManager $session,
        Dispatcher $events)
    {
        $this->productService = $productService;
        $this->attributeService = $attributeService;
        $this->promotionService = $promotionService;
        $this->events = $events;
        $this->session = $session;
    }

    /**
     * Add product.
     *
     * @param  int $productId
     * @param  int $productItemId
     * @param  int $quantity
     * @param  array $attributes
     *
     * @return mixed
     */
    public function add($productId, $productItemId, $quantity, array $attributes)
    {
        $clientId = Agent::clientId();

        $skuId = $this->productService->getProductSkuId($productId, $attributes);

        $attributes = $this->productService->sortProductAttributes($productId, $attributes);

        if (!$skuId) {
            return false;
        }

        $item = $this->createModel()->newQuery()->where(['client_id' => $clientId, 'sku_id' => $skuId])->first();
        if ($item) {
            $item = $this->update($item->id, ['quantity' => $item->quantity + $quantity]);
            $this->events->dispatch(new CartUpdated($item));
        } else {
            $productItem = $this->productService->findProductItem($productId, $productItemId);
            if (!$productItem) {
                return false;
            }

            $item = $this->create([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'quantity' => $quantity,
                'customer_id' => (int) Auth::id(),
                'client_id' => $clientId,
                'price' => $productItem->price,
                'market_price' => $productItem->market_price,
                'weight' => $productItem->weight,
                'attrs' => $attributes,
            ]);

            $this->events->dispatch(new CartCreated($item));
        }

        $this->refresh();

        return $item;
    }

    /**
     * Remove from cart.
     *
     * @param $cartId
     *
     * @return mixed
     */
    public function remove($cartId)
    {
        if (($cart = $this->find($cartId)) && ($cart->client_id == Agent::clientId())) {
            $cart->delete();
            $this->refresh();
            $this->events->dispatch(new CartRemoved($cart));
            return $cart->quantity;
        }

        return 0;
    }

    /**
     * Set cart item quantity.
     *
     * @param $cartId
     * @param $quantity
     * @return mixed
     */
    public function setItemQuantity($cartId, $quantity)
    {
        if ($quantity > 0 && ($cart = $this->find($cartId)) && $cart->quantity != $quantity) {
            $this->update($cartId, ['quantity' => $quantity]);
            $this->refresh();
            $this->events->dispatch(new CartUpdated($cart));
        }

        return isset($cart) ? $cart : null;
    }

    /**
     * Update cart item attributes.
     *
     * @param $cartId
     * @param $attributes
     * @return mixed
     */
    public function updateItemAttributes($cartId, $attributes)
    {
        if ($cart = $this->find($cartId)) {
            $skuId = $this->productService->getProductSkuId($cart->product_id, $attributes);
            if (!$skuId) {
                return false;
            }
            if ($skuId != $cart->sku_id) {
                if ($this->createModel()->where(['client_id' => $cart->client_id, 'sku_id' => $skuId])->exists()) {
                    return false;
                }
                $productItem = $this->productService->getProductItem($cart->product_id, $attributes);
                $cart = $this->update($cartId, [
                    'sku_id' => $skuId,
                    'price' => $productItem->price,
                    'market_price' => $productItem->market_price,
                    'attrs' => $attributes,
                ]);
            }

        }

        return $cart;
    }

    /**
     * cart lists.
     *
     * @param null $clientId
     * @return \Viviniko\Cart\Services\Collection
     */
    public function getItems($clientId = null)
    {
        $items = new Collection($this->findBy('client_id', $clientId ?? Agent::clientId())->all());
        $items->setCoupon($this->session->get('cart.coupon.code', null), $this->session->get('cart.coupon.discount', 0));

        return $items;
    }

    /**
     * Get cart quantity count.
     *
     * @param null $clientId
     * @return int
     */
    public function getQuantity($clientId = null)
    {
        if (!$this->session->has('cart.quantity')) {
            $this->session->put('cart.quantity', $this->getItems($clientId)->getQuantity());
        }

        return $this->session->get('cart.quantity');
    }

    public function refresh()
    {
        $this->session->remove('cart.quantity');
        if ($this->getQuantity() == 0) {
            $this->setCoupon(null);
        } else {
            try {
                $this->setCoupon($this->session->get('cart.coupon.code'));
            } catch (\Exception $e) {
                $this->setCoupon('');
            }
        }
    }

    /**
     * Remove cart items.
     *
     * @param null $clientId
     * @return mixed
     */
    public function clear($clientId = null)
    {
        $this->createModel()->where('client_id', $clientId ?? Agent::clientId())->delete();
        $this->refresh();
    }

    /**
     * Sync customer client id.
     *
     * @param $customerId
     * @param null $clientId
     * @return mixed
     */
    public function syncCustomerClientId($customerId, $clientId = null)
    {
        return $this->createModel()->newQuery()->where('client_id', $clientId ?? Agent::clientId())->update(['customer_id' => $customerId]);
    }

    /**
     * Set coupon.
     *
     * @param $coupon
     * @return mixed
     * @throws \Exception
     */
    public function setCoupon($coupon)
    {
        $coupon = trim($coupon);
        if (!empty($coupon)) {
            $this->session->put('cart.coupon.discount', price_number($this->promotionService->getCouponDiscountAmount($this->getItems(), $coupon)));
            $this->session->put('cart.coupon.code', $coupon);
        } else {
            $this->session->remove('cart.coupon.code');
            $this->session->remove('cart.coupon.discount');
        }

        return $this;
    }

    /**
     * Get cart statistics.
     *
     * @param null $clientId
     * @return mixed
     */
    public function getStatistics($clientId = null)
    {
        return $this->getItems($clientId)->getStatistics();
    }
}