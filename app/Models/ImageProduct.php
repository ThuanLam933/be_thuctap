<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class ImageProduct extends Model
{
    protected $table = 'image_products';

    protected $fillable = [
        'product_detail_id',
        'url_image',
        'sort_order',
        'description',
    ];

    protected $casts = [
    ];

    public function productDetail()
    {
        return $this->belongsTo(\App\Models\Product_detail::class, 'product_detail_id');
    }

    public function getFullUrlAttribute()
    {
        return $this->getUrlAttribute();
    }

    public function getUrlAttribute()
    {
        if (! $this->url_image) {
            return null;
        }

        if (preg_match('/^https?:\\/\\//i', $this->url_image)) {
            return $this->url_image;
        }

        $baseUrl = 'http://127.0.0.1:8000';

        return $baseUrl . '/storage/' . ltrim($this->url_image, '/');
    }
}
