<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Notifikasi untuk user tertentu
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Jenis notifikasi, misal: booking_created, booking_approved
            $table->string('type');

            // Isi notifikasi
            $table->text('message');

            // Status sudah dibaca atau belum
            $table->boolean('is_read')->default(false);

            // Optional: relasi ke booking
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            // Audit trail
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->string('restored_by')->nullable();
            $table->timestamp('restored_at')->nullable(); // optional
            $table->boolean('is_active')->default(true);

            // Soft delete
            $table->timestamps(); // created_at & updated_at
            $table->softDeletes(); // deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
