<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_at',
        'token_id',
    ];

    public $timestamps = false; // 不使用 created_at / updated_at

    protected $casts = [
        'login_at' => 'datetime', // 确保 login_at 转为 Carbon 对象
    ];
}