<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'email_logs';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'booking_id',
        'email_template_id',
        'to',
        'cc',
        'bcc',
        'status',
        'sent_at',
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
        'sent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke Booking
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Relasi ke EmailTemplate
     */
    public function emailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class);
    }
}
