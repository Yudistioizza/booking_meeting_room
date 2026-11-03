<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'room_id',
        'created_by',
        'approved_by',
        'recurring_booking_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'status',
        'attendees',
        'attendee_count',
        'cancel_reason',
        'cancelled_at',
        'ics_uid',
        'ics_sequence',
        'external_provider',
        'outlook_event_id',
        'outlook_invitation_sent',
        'updated_by',
        'deleted_by',
        'restored_by',
        'restored_at',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'outlook_invitation_sent' => 'boolean',
        'attendees' => 'array',
        'cancelled_at' => 'datetime',
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

    /**
     * Relasi ke User yang membuat booking
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke User yang approve booking
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi ke Booking Recurring
     */
    public function recurringBooking()
    {
        return $this->belongsTo(RecurringBooking::class);
    }

    /**
     * Relasi ke audit trail users
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function restorer()
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    /**
     * Relasi ke approvals
     */
    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    /* ───────────── query helpers ───────────── */

    /** Bookings that hold a room (waiting or confirmed). */
    public function scopeHolding($q)
    {
        return $q->whereIn('status', ['pending', 'approved']);
    }

    /** Bookings overlapping a time window. */
    public function scopeOverlapping($q, $start, $end)
    {
        return $q->where('start_time', '<', $end)->where('end_time', '>', $start);
    }

    /** Bookings the user organizes or is invited to. */
    public function scopeInvolving($q, User $user)
    {
        return $q->where(fn ($w) => $w->where('created_by', $user->id)->orWhereJsonContains('attendees', strtolower($user->email)));
    }

    /* ───────────── presentation ───────────── */

    public const STATUS = [
        'approved' => ['Dikonfirmasi', 'badge-brand'],
        'pending' => ['Menunggu persetujuan', 'badge-wait'],
        'rejected' => ['Ditolak', 'badge-busy'],
        'cancelled' => ['Dibatalkan', 'badge-slate'],
    ];

    public function statusLabel(): string
    {
        return self::STATUS[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS[$this->status][1] ?? 'badge-slate';
    }

    public function durationLabel(): string
    {
        $m = (int) $this->start_time->diffInMinutes($this->end_time);

        return ($m >= 60 ? intdiv($m, 60) . ' jam' : '') . ($m % 60 ? ($m >= 60 ? ' ' : '') . $m % 60 . ' mnt' : '');
    }

    public function isLive(): bool
    {
        return $this->status === 'approved' && $this->start_time->lte(now()) && $this->end_time->gt(now());
    }
}
