<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'email',
        'address',
        'phone',
    ];

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'suppliers_id');
    }
}
