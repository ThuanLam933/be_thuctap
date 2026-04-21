<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Supplier;
use App\Models\ReceiptDetail;
class Receipt extends Model
{
    use HasFactory;

    protected $table = 'receipts';

    protected $fillable = [
        'user_id',
        'suppliers_id',
        'note',
        'total_price',
        'import_date',
    ];

    protected $casts = [
        'import_date' => 'date',
        'total_price' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'suppliers_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function details()
    {
        return $this->hasMany(ReceiptDetail::class);
    }
}
