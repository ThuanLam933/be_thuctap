<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReceiptDetail extends Model
{
    use HasFactory;

    protected $table = 'receipt_details';

    protected $fillable = [
        'product_detail_id',
        'receipt_id',
        'quantity',
        'price',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function productDetail()
    {
        return $this->belongsTo(\App\Models\Product_detail::class);
    }
}
