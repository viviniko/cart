<?php

namespace Viviniko\Cart\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Viviniko\Agent\Facades\Agent;
use Viviniko\Cart\Collection;
use Viviniko\Cart\Events\CartCreated;
use Viviniko\Cart\Events\CartRemoved;
use Viviniko\Cart\Events\CartUpdated;
use Viviniko\Cart\Repositories\Cart\CartRepository;
use Viviniko\Catalog\Contracts\Catalog;
use Viviniko\Promotion\Services\PromotionService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;

class CartServiceImpl implements CartService
{
    /**
     * @var \Viviniko\Cart\Repositories\Cart\CartRepository
     */
    protected $cartRepository;

    /**
     * @var \Viviniko\Catalog\Contracts\Catalog
     */
    protected $catalog;

    /**
     * @var \Viviniko\Promotion\Services\PromotionService
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
     * @var int
     */
    protected $cacheMinutes = 2;

    /**
     * EloquentCart constructor.
     * @param \Viviniko\Cart\Repositories\Cart\CartRepository
     * @param \Viviniko\Catalog\Contracts\Catalog
     * @param \Viviniko\Promotion\Services\PromotionService $promotionService
     * @param \Illuminate\Session\SessionManager $session
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(
        CartRepository $cartRepository,
        Catalog $catalog,
        PromotionService $promotionService,
        SessionManager $session,
        Dispatcher $events)
    {
        $this->cartRepository = $cartRepository;
        $this->catalog = $catalog;
        $this->promotionService = $promotionService;
        $this->events = $events;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function add($itemId, $quantity)
    {
        $clientId = Agent::clientId();

        $cart = $this->cartRepository
            ->findBy(array_merge(Auth::check() ? ['customer_id' => Auth::id()] : ['client_id' => $clientId], ['item_id' => $itemId]));

        if ($cart) {
            $cart = $this->cartRepository->update($cart->id, ['quantity' => $cart->quantity + $quantity]);
            $this->events->dispatch(new CartUpdated($cart));
        } else {
            $product = $this->catalog->getProductByItemId($itemId);
            if (!$product) {
                return false;
            }
            $item = $product->items->where('id', $itemId)->first();
            if (!$item) {
                return false;
            }

            $cart = $this->cartRepository->create([
                'product_id' => $product->id,
                'item_id' => $item->id,
                'category_id' => $product->category_id,
                'customer_id' => (int) Auth::id(),
                'client_id' => $clientId,
                'amount' => $item->amount->discount($item->discount)->value,
                'quantity' => $quantity,
            ]);

            $this->events->dispatch(new CartCreated($cart));
        }

        $this->refresh();

        return $cart;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($cartId)
    {
        if (($cart = $this->cartRepository->find($cartId)) && ((Auth::check() && $cart->customer_id == Auth::id()) || $cart->client_id == Agent::clientId())) {
            $this->cartRepository->delete($cartId);
            $this->refresh();
            $this->events->dispatch(new CartRemoved($cart));
            return $cart->quantity;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function setItemQuantity($cartId, $quantity)
    {
        if ($quantity > 0 && ($cart = $this->cartRepository->find($cartId)) && $cart->quantity != $quantity) {
            $this->cartRepository->update($cartId, ['quantity' => $quantity]);
            $this->refresh();
            $this->events->dispatch(new CartUpdated($cart));
        }

        return isset($cart) ? $cart : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($clientId = null)
    {
        $filters = [];
        if ($clientId) {
            $filters['client_id'] = $clientId;
        } else if (Auth::check()) {
            $filters['customer_id'] = Auth::id();
        } else {
            $filters['client_id'] = Agent::clientId();
        }

        $items = $this->cartRepository->findAllBy($filters, null, ['id'])->map(function($item) {
            return $this->getCartItem($item->id);
        })->filter();

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
     * {@inheritdoc}
     */
    public function getQuantity($clientId = null)
    {
        if (!$this->session->has('cart.quantity')) {
            $this->session->put('cart.quantity', $this->getItems($clientId)->getQuantity());
        }

        return $this->session->get('cart.quantity');
    }

    /**
     * {@inheritdoc}
     */
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
     * {@inheritdoc}
     */
    public function syncCustomerClientId($customerId, $clientId = null)
    {
        $clientId = $clientId ?? Agent::clientId();
        $clientCartItems = $this->cartRepository->findAllBy('client_id', $clientId);
        foreach ($clientCartItems as $clientCartItem) {
            if ($clientCartItem->customer_id != $customerId) {
                if ($oldCart = $this->cartRepository->findBy(['customer_id' => $customerId, 'item_id' => $clientCartItem->item_id])) {
                    $this->cartRepository->update($oldCart->id, ['quantity' => $clientCartItem->quantity, 'client_id' => '']);
                    $this->cartRepository->delete($clientCartItem->id);
                } else {
                    $this->cartRepository->update($clientCartItem->item_id, ['customer_id' => $customerId, 'client_id' => '']);
                }

            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearCustomerClientId($customerId)
    {
        $this->cartRepository->updateByCustomerId($customerId, ['client_id' => '']);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getStatistics($clientId = null)
    {
        return $this->getItems($clientId)->getStatistics();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(array $ids)
    {
        return $this->cartRepository->deleteAll($ids);
    }

    public function getCartItem($id)
    {
        $item = Cache::remember("cart.item:{$id}", Config::get('cache.ttl', $this->cacheMinutes), function () use ($id) {
            return $this->cartRepository->find($id);
        });

        $product = $this->catalog->getProduct($item->product_id);
        $productItem = $product ? $product->items->where('id', $id)->first() : null;

        if (!$productItem || !$item) {
            return null;
        }

        $item->amount = $productItem->amount->discount($productItem->discount);
        $item->subtotal = $item->amount->mul($item->quantity);
        $item->weight = $productItem->weight;
        $item->gross_weight = $productItem->weight * $item->quantity;
        $item->desc_specs = collect([]);
        foreach ($productItem->specs as $specId) {
            $prodSpec = $product->specs->where('id', $specId)->first();
            $prodSpecGroup = $product->specGroups->where('id', $prodSpec->group_id)->first();
            $item->desc_specs->put($prodSpecGroup->name, $prodSpec->name);
        }
        $item->sku = $productItem->sku;
        $item->picture = $productItem->picture;
        $item->product = $product;
        $item->category = $this->catalog->getCategory($item->category_id);

        return $item;
    }
}