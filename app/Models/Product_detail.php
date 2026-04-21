<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;




class Product_detail extends Model
{
    protected $fillable = [
       'color_id',
       'size_id',
       'product_id',
       'price',
       'quantity',
       'status',
       
    ];

    public function color()
    {
        return $this->belongsTo(Colors::class);
    }
    

    public function size()
    {
        return $this->belongsTo(Sizes::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(\App\Models\ImageProduct::class, 'product_detail_id');
    }
    



}