<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    protected $fillable = [
       'slug',
       'name'
    ];
    public function product()
    {
        return $this->hasMany(Product::class, 'categories_id');
    }
}
