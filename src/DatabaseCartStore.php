<?php

namespace Viviniko\Cart;

class DatabaseCartStore extends AbstractCartStore
{
    private $model;

    public function __construct($model)
    {
        if (is_string($model)) {
            $class = '\\'.ltrim($model, '\\');
            $model = new $class;
        }
        $this->model = $model;
    }

    /**
     * @param array $items
     * @param $ttl
     * @return void
     */
    public function setItems(array $items, $ttl)
    {
        $this->getModel()->newQuery()->updateOrCreate(['client_id' => $this->getClientId()], ['items' => $items]);
    }

    /**
     * @return array
     */
    public function getItems()
    {
        $model = $this->getModel()->newQuery()->where(['client_id' => $this->getClientId()])->get(['items'])->first();

        return empty($model) ? [] : $model->items;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return count($this->getItems());
    }

    /**
     * @return void
     */
    public function forget()
    {
        $this->getModel()->newQuery()->where(['client_id' => $this->getClientId()])->delete();
    }

    /**
     * @return \Viviniko\Support\Database\Eloquent\Model
     */
    public function getModel()
    {
        return $this->model;
    }
}