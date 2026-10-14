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
        if (! Schema::hasColumn('me_users', 'is_active')) {
            Schema::table('me_users', function (Blueprint $table) {
                $table->boolean('is_active')->default(false);
                $table->timestamp('last_login_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('me_users', 'is_active')) {
            Schema::table('me_users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
        if (Schema::hasColumn('me_users', 'last_login_at')) {
            Schema::table('me_users', function (Blueprint $table) {
                $table->dropColumn('last_login_at');
            });
        }
    }
};
