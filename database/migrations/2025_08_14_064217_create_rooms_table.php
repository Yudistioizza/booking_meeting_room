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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id(); // Primary Key
            $table->string('name')->unique(); // Nama ruangan
            $table->string('location')->nullable(); // Lokasi gedung/lantai
            $table->integer('capacity')->default(0); // Kapasitas peserta
            $table->text('equipment')->nullable(); // Fasilitas, misal: Proyektor, Whiteboard

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
        Schema::dropIfExists('rooms');
    }
};
