<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {        return [];
    }
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
     public function emailOtps(): HasMany
    {
        return $this->hasMany(EmailOtp::class);
    }
     
   
}
