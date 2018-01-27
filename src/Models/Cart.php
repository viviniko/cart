<?php

namespace Viviniko\Cart\Models;

use Illuminate\Support\Facades\Config;
use Viviniko\Support\Database\Eloquent\Model;

class Cart extends Model
{
    protected $tableConfigKey = 'cart.cart_table';

    protected $fillable = [
        'product_id', 'item_id', 'category_id', 'customer_id', 'client_id', 'price', 'cart_price', 'quantity', 'weight'
    ];

    protected $casts = [
        'cart_price' => 'float',
        'price' => 'float',
        'weight' => 'float',
    ];

    protected $appends = [
        'name', 'sku', 'url', 'picture', 'subtotal', 'attribute_values'
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

    public function getSkuAttribute()
    {
        return data_get($this->item, 'sku');
    }

    public function getPictureAttribute()
    {
        return app(\Viviniko\Catalog\Contracts\ProductService::class)->getProductPicture($this->product_id, $this->attrs);
    }

    public function getSubtotalAttribute()
    {
        return $this->price * $this->quantity;
    }

    public function getGrossWeightAttribute()
    {
        return $this->weight * $this->quantity;
    }

    public function getDescriptionAttribute()
    {
        $description = [];
        foreach ($this->getAttributeValuesAttribute() as $name => $value) {
            $description[] = "$name: $value";
        }

        return implode('; ', $description);
    }

    public function getAttributeValuesAttribute()
    {
        return app(\Viviniko\Catalog\Contracts\AttributeService::class)->findIn($this->attrs)->sort(function ($a, $b) {
            foreach ($this->attrs as $attr) {
                if ($a->id == $attr) {
                    return -1;
                }
                if ($b->id == $attr) {
                    return 1;
                }
            }
            return 0;
        })->pluck('value', 'group.text_prompt');
    }
}