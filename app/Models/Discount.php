<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'usage_limit',
        'usage_count',
        'type',
        'code',
        'value',
        'min_total',
    ];
    public function orders(){
        return $this->hasMany(Order::class, 'discount_id');
    }
}
