<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colors extends Model
{
    protected $fillable = [
        'name',
      
    ];
    public function productdetail(){
        return $this->hasMany(Product_detail::class, 'color_id');
    }
}
