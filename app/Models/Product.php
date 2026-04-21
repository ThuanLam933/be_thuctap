<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'image_url',
        'categories_id',
    ];
    public function categories(){
        return $this->belongsTo(Categories::class, 'categories_id');
    }
    public function details(){
        return $this->hasMany(Product_detail::class, 'product_id');
    }
    public function reviews(){
        return $this->hasMany(Review::class, 'product_id');
    }
}
