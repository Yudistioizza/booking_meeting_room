<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'summary', 'meta', 'ip_address', 'created_at'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Append-only trail of who did what; never throws into the caller. */
    public static function record(string $action, ?Model $subject, string $summary, array $meta = [], ?User $actor = null): void
    {
        try {
            static::create([
                'user_id' => ($actor ?? auth()->user())?->id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'summary' => $summary,
                'meta' => $meta ?: null,
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
