<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $table = 'inventory_logs';

    protected $fillable = [
        'product_detail_id',
        'change',
        'quantity_before',
        'quantity_after',
        'type',
        'related_id',
        'user_id',
        'note',
    ];

    public function productDetail()
    {
        return $this->belongsTo(\App\Models\Product_detail::class, 'product_detail_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public static function forReceipt($productDetailId, $change, $before, $after, $receiptId = null, $userId = null, $note = null)
    {
        return static::create([
            'product_detail_id' => $productDetailId,
            'change' => $change,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'type' => 'receipt',
            'related_id' => $receiptId,
            'user_id' => $userId,
            'note' => $note,
        ]);
    }
}
