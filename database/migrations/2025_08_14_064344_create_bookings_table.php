<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            
            // Relasi
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recurring_booking_id')->nullable()->constrained('recurring_bookings')->nullOnDelete();

            // Data booking
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->json('attendees')->nullable();           // external/internal guest emails
            $table->unsignedSmallInteger('attendee_count')->default(1);
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Calendar interoperability (RFC 5545)
            $table->string('ics_uid')->unique();
            $table->unsignedInteger('ics_sequence')->default(0);

            // Outlook integration
            $table->string('outlook_event_id')->nullable();
            $table->string('external_provider', 30)->nullable(); // graph | null
            $table->boolean('outlook_invitation_sent')->default(false);

            // Audit trail (tanpa duplikasi created_by)
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->foreignId('restored_by')->nullable()->constrained('users');
            $table->timestamp('restored_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Timestamps & soft delete
            $table->timestamps(); // created_at & updated_at
            $table->softDeletes(); // deleted_at

            // Conflict checks always filter by room and time window
            $table->index(['room_id', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
