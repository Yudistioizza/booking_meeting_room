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
        Schema::create('room_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();

            // Hari tersedia, misal: "Mon,Tue,Wed"
            $table->string('available_days')->nullable();

            // Jam tersedia, misal: "08:00-12:00,13:00-17:00"
            $table->string('available_hours')->nullable();

            // Prioritas untuk smart suggestion
            $table->integer('priority')->default(1);


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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_availability_rules');
    }
};
