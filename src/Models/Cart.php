<?php

namespace Viviniko\Cart\Models;

use Illuminate\Support\Facades\Config;
use Viviniko\Support\Database\Eloquent\Model;

class Cart extends Model
{
    protected $tableConfigKey = 'cart.cart_table';

    protected $fillable = [
        'product_id', 'item_id', 'category_id', 'customer_id', 'client_id', 'quantity'
    ];

    protected $appends = [
        'name', 'sku', 'url', 'amount', 'currency', 'subtotal', 'desc_specs'
    ];

    protected $hidden = [
        'product'
    ];

    public function category()
    {
        return $this->belongsTo(Config::get('catalog.category'), 'category_id');
    }

    public function product()
    {
        return $this->belongsTo(Config::get('catalog.product'), 'product_id');
    }

    public function item()
    {
        return $this->belongsTo(Config::get('catalog.item'), 'item_id');
    }

    public function getNameAttribute()
    {
        return data_get($this->product, 'name');
    }

    public function getUrlAttribute()
    {
        return data_get($this->product, 'url');
    }

    public function getAmountAttribute()
    {
        return data_get($this->item, 'amount');
    }

    public function getCurrencyAttribute()
    {
        return data_get($this->item, 'currency');
    }

    public function getWeightAttribute()
    {
        return data_get($this->item, 'weight');
    }

    public function getSpecsAttribute()
    {
        return $this->item->specs->pluck('id')->toArray();
    }

    public function getSkuAttribute()
    {
        return data_get($this->item, 'sku');
    }

    public function getCoverAttribute()
    {
        return data_get($this->item, 'cover');
    }

    public function getSubtotalAttribute()
    {
        return $this->getAmountAttribute() * $this->quantity;
    }

    public function getGrossWeightAttribute()
    {
        return $this->getWeightAttribute() * $this->quantity;
    }

    public function getDescSpecsAttribute()
    {
        return data_get($this->item, 'desc_specs');
    }

}