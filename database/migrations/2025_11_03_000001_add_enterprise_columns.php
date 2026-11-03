<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('equipment');
            $table->unsignedSmallInteger('max_duration_minutes')->nullable()->after('requires_approval');
            $table->unsignedSmallInteger('buffer_minutes')->default(0)->after('max_duration_minutes');
            $table->string('calendar_email')->nullable()->after('buffer_minutes'); // Exchange room mailbox
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('email');
            $table->string('calendar_token', 64)->nullable()->unique()->after('remember_token'); // personal .ics feed
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary');
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['department', 'calendar_token']));
        Schema::table('rooms', fn (Blueprint $t) => $t->dropColumn(['requires_approval', 'max_duration_minutes', 'buffer_minutes', 'calendar_email']));
    }
};
