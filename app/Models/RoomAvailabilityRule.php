<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomAvailabilityRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'room_availability_rules';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'room_id',
        'available_days',
        'available_hours',
        'priority',
        'created_by',
        'updated_by',
        'deleted_by',
        'restored_by',
        'restored_at',
        'is_active',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'restored_at' => 'datetime',
    ];

    /**
     * Relasi ke Room
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
