<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    use HasFactory;

    public $timestamps = false; // karena hanya ada created_at manual

    protected $fillable = [
        'email',
        'token',
        'created_at',
        'created_by',
    ];
}
