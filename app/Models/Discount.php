<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
     protected $fillable = [
  'code','name','type','value','min_total',
  'usage_limit','usage_count','is_active','start_at','end_at'
];

    public function order()
    {
        return $this->hasMany(Order::class);
    }
}
