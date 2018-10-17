<?php

namespace Viviniko\Cart\Repositories\Cart;

use Illuminate\Support\Facades\Config;
use Viviniko\Repository\EloquentRepository;

class EloquentCart extends EloquentRepository implements CartRepository
{
    public function __construct()
    {
        parent::__construct(Config::get('cart.cart'));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByClientId($clientId)
    {
        return $this->createQuery()->where('client_id', $clientId)->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function updateByClientId($clientId, array $data)
    {
        return $this->createQuery()->where('client_id', $clientId)->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function updateByCustomerId($customerId, array $data)
    {
        return $this->createQuery()->where('customer_id', $customerId)->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(array $ids)
    {
        $this->createQuery()->whereIn('id', $ids)->delete();
    }
}