<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'is_internal')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->boolean('is_internal')->default(false)->after('is_recommended');
                $table->index('is_internal', 'idx_subscription_plans_is_internal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'is_internal')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropIndex('idx_subscription_plans_is_internal');
                $table->dropColumn('is_internal');
            });
        }
    }
};
