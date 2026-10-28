<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('department', 100)->nullable()->after('phone');
            $table->string('profile_photo_path')->nullable()->after('department');
            $table->boolean('is_active')->default(true)->after('profile_photo_path')->index();
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('deactivated_at');
            $table->json('notification_preferences')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['phone', 'department', 'profile_photo_path', 'is_active', 'deactivated_at', 'last_login_at', 'notification_preferences']);
        });
    }
};
