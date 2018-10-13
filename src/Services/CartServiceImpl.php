<?php

namespace Viviniko\Cart\Services;

use Viviniko\Agent\Facades\Agent;
use Viviniko\Cart\Events\CartCreated;
use Viviniko\Cart\Events\CartRemoved;
use Viviniko\Cart\Events\CartUpdated;
use Viviniko\Cart\Repositories\Cart\CartRepository;
use Viviniko\Catalog\Contracts\AttributeService;
use Viviniko\Catalog\Services\ItemService;
use Viviniko\Catalog\Services\ProductService;
use Viviniko\Promotion\Contracts\PromotionService;
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
     * @var \Viviniko\Catalog\Services\ProductService
     */
    protected $productService;

    /**
     * @var \Viviniko\Catalog\Services\ItemService
     */
    protected $itemService;

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
     * @param \Viviniko\Cart\Repositories\Cart\CartRepository
     * @param \Viviniko\Catalog\Services\ItemService
     * @param \Viviniko\Catalog\Services\ProductService $productService
     * @param \Viviniko\Catalog\Contracts\AttributeService $attributeService
     * @param \Viviniko\Promotion\Contracts\PromotionService $promotionService
     * @param \Illuminate\Session\SessionManager $session
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(
        CartRepository $cartRepository,
        ItemService $itemService,
        ProductService $productService,
        AttributeService $attributeService,
        PromotionService $promotionService,
        SessionManager $session,
        Dispatcher $events)
    {
        $this->cartRepository = $cartRepository;
        $this->productService = $productService;
        $this->itemService = $itemService;
        $this->attributeService = $attributeService;
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
            $item = $this->itemService->find($itemId);
            if (!$item) {
                return false;
            }

            $cart = $this->cartRepository->create([
                'product_id' => $item->product_id,
                'item_id' => $item->id,
                'category_id' => $item->product->category_id,
                'customer_id' => (int) Auth::id(),
                'client_id' => $clientId,
                'amount' => $item->amount->value,
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
            $filters['cusomter_id'] = Auth::id();
        } else {
            $filters['client_id'] = Agent::clientId();
        }

        $items = $this->cartRepository->findAllBy($filters);

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
}