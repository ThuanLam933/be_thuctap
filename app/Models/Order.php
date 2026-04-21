<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
     protected $fillable = [
       'user_id',
       'discount_id',
       'order_code',
       'name',
       'email',
       'phone',
       'address',
       'note',
       'total_price',
       'status_stock',
       'payment_method',
       'status',
       'status_method'
    ];

     public function user()
    {
        return $this->belongsTo(User::class);
    }
     public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
    public function items()
{
    return $this->hasMany(\App\Models\OrderDetail::class, 'order_id');
}



}
