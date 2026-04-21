<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sizes extends Model
{
    protected $fillable = ['name'];
    public function productDetails()
    {
        return $this->hasMany(Product_detail::class, 'size_id');
    }
}
