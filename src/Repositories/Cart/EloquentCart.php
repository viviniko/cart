<?php

namespace Viviniko\Cart\Repositories\Cart;

use Viviniko\Repository\SimpleRepository;

class EloquentCart extends SimpleRepository implements CartRepository
{
    protected $modelConfigKey = 'cart.cart';

    /**
     * {@inheritdoc}
     */
    public function deleteByClientId($clientId)
    {
        return $this->createModel()->where('client_id', $clientId)->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function updateByClientId($clientId, array $data)
    {
        return $this->createModel()->where('client_id', $clientId)->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function updateByCustomerId($customerId, array $data)
    {
        return $this->createModel()->where('customer_id', $customerId)->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(array $ids)
    {
        $this->createModel()->whereIn('id', $ids)->delete();
    }
}