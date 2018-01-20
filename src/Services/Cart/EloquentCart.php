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
     * @var \Common\Catalog\Contracts\ProductService
     */
    protected $productService;

    /**
     * @var \Common\Catalog\Contracts\AttributeService
     */
    protected $attributeService;

    /**
     * @var \Common\Promotion\Contracts\PromotionService
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
     * @param \Common\Catalog\Contracts\ProductService $productService
     * @param \Common\Catalog\Contracts\AttributeService $attributeService
     * @param \Common\Promotion\Contracts\PromotionService $promotionService
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

        $item = $this->createModel()->newQuery()->where(array_merge(Auth::check() ? ['customer_id' => Auth::id()] : ['client_id' => $clientId], ['sku_id' => $skuId]))->first();
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
                'price' => $productItem->getOriginal('price'),
                'market_price' => $productItem->getOriginal('market_price'),
                'weight' => $productItem->getOriginal('weight'),
                'attrs' => $attributes,
            ]);

            $this->events->dispatch(new CartCreated($item));
        }

        $this->refresh();

        return $item;
    }

    public function updateProduct($productId, $skuId)
    {
        $productItemId = $skuId;
        $productItem = $this->productService->findProductItem($productId, $productItemId);
        if (!$productItem) {
            return false;
        }
        $this->createModel()->where('product_id', $productId)->get()->each(function ($item) use ($productItem) {
            if (($item->getOriginal('price') != $productItem->getOriginal('price') ||
                    $item->getOriginal('market_price') != $productItem->getOriginal('market_price') ||
                    $item->getOriginal('weight') != $productItem->getOriginal('weight')) &&
                $item->sku_id == $this->productService->getProductSkuId($productItem->product_id, $item->attrs)
            ) {
                $this->update($item->id, [
                    'price' => $productItem->getOriginal('price'),
                    'market_price' => $productItem->getOriginal('market_price'),
                    'weight' => $productItem->getOriginal('weight'),
                ]);
            }

        });

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
        if (($cart = $this->find($cartId)) && ((Auth::check() && $cart->customer_id == Auth::id()) || $cart->client_id == Agent::clientId())) {
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
                    'price' => $productItem->getOriginal('price'),
                    'market_price' => $productItem->getOriginal('market_price'),
                    'weight' => $productItem->getOriginal('weight'),
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
     * @return \Common\Cart\Services\Collection
     */
    public function getItems($clientId = null)
    {
        $items = !$clientId && Auth::check() ? $this->findBy('customer_id', Auth::id()) : $this->findBy('client_id', $clientId ?? Agent::clientId());
        $items = new Collection($items->sortByDesc('created_at')->all());
        if ($this->session->has('cart.quantity') && $this->session->get('cart.quantity') != $items->count()) {
            $this->session->remove('cart.quantity');
            if ($coupon = $this->session->get('cart.coupon.code')) {
                try {
                    $this->session->put('cart.coupon.discount', $this->promotionService->getCouponDiscountAmount($items, $coupon));
                } catch (\Exception $e) {
                    $this->session->remove('cart.coupon.code');
                    $this->session->remove('cart.coupon.discount');
                }
            }
        }

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
        $this->createModel()->where(Auth::check() ? ['customer_id' => Auth::id()] : ['client_id' => $clientId ?? Agent::clientId()])->delete();
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
        $clientId = $clientId ?? Agent::clientId();
        $this->createModel()->newQuery()->where('client_id', $clientId)->update(['customer_id' => $customerId]);
        $this->createModel()->newQuery()->where('customer_id', $customerId)->where('client_id', '!=', $clientId)->get()->each(function ($item) use ($customerId, $clientId) {
            if ($this->createModel()->newQuery()->where(['client_id' => $clientId, 'sku_id' => $item->sku_id])->exists()) {
                $this->delete($item->id);
            } else {
                $this->update($item->id, ['client_id' => $clientId]);
            }
        });
    }

    public function clearCustomerClientId($customerId)
    {
        $this->createModel()->newQuery()->where('customer_id', $customerId)->update(['client_id' => '']);
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
            $this->session->put('cart.coupon.discount', $this->promotionService->getCouponDiscountAmount($this->getItems(), $coupon));
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

    public function deleteAll(array $ids)
    {
        $this->createModel()->whereIn('id', $ids)->delete();
    }
}