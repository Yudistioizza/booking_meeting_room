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
        Schema::create('recurring_bookings', function (Blueprint $table) {
            $table->id();
            $table->enum('frequency', ['daily','weekly','monthly']);
            $table->integer('interval')->default(1); // setiap 1 hari/minggu/bulan
            $table->string('days_of_week')->nullable(); // misal: "Mon,Wed,Fri" untuk weekly
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Audit trail
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_bookings');
    }
};
