<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_at',
        'token_id'
    ];

    public $timestamps = false; // 我们手动控制时间戳
}