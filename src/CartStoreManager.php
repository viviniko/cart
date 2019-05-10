<?php

namespace Viviniko\Cart;

use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use Viviniko\Cart\Events\CartStoreChanged;

class CartStoreManager extends Manager
{
    /**
     * @var Collection
     */
    private $stores;

    /**
     * @var string
     */
    private $currentStoreName;

    /**
     * CartStoreManager constructor.
     * @param $app
     * @param string $storeName
     */
    public function __construct($app, $storeName)
    {
        parent::__construct($app);
        $this->currentStoreName = $storeName;
        $this->stores = new Collection();
    }

    public function getStore($key = null)
    {
        if ($key == null) {
            $key = $this->currentStoreName;
        }
        if (!$this->stores->has($key)) {
            $this->stores->put($key, $this->driver($this->app['config']["{$this->currentStoreName}.driver"]));
        }

        return $this->stores->get($key);
    }

    public function createDatabaseDriver()
    {
        return new DatabaseCartStore($this->app['config']['cart.store_drivers.database.model']);
    }

    public function createCookieDriver()
    {
        return new CookieCartStore();
    }

    public function createRedisDriver()
    {
        return new RedisCartStore($this->app['config']['cart.store_drivers.redis.conn']);
    }

    public function changeCurrentStore($name)
    {
        if ($name != $this->currentStoreName && !empty($this->currentStoreName)) {
            $this->app['events']->dispatch(new CartStoreChanged($this->getStore($this->currentStoreName), $this->getStore($name)));
            $this->currentStoreName = $name;
        }

        return $this;
    }

    /**
     * Get the default cache driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['cart.default_store.driver'];
    }

    /**
     * Set the default cache driver name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['cart.default_store.driver'] = $name;
    }
}