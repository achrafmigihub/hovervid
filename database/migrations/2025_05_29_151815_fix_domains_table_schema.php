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
        Schema::table('domains', function (Blueprint $table) {
            // Check if domain_name exists and rename it to domain
            if (Schema::hasColumn('domains', 'domain_name') && !Schema::hasColumn('domains', 'domain')) {
                $table->renameColumn('domain_name', 'domain');
            }
            
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('domains', 'status')) {
                $table->string('status')->default('inactive')->after('platform');
            }
            
            if (!Schema::hasColumn('domains', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('status');
            }
            
            if (!Schema::hasColumn('domains', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_active');
            }
            
            if (!Schema::hasColumn('domains', 'api_key')) {
                $table->uuid('api_key')->nullable()->after('is_verified');
            }
            
            if (!Schema::hasColumn('domains', 'verification_token')) {
                $table->string('verification_token', 32)->nullable()->after('api_key');
            }
            
            if (!Schema::hasColumn('domains', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('verification_token');
            }
            
            if (!Schema::hasColumn('domains', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('last_checked_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // Rename back to domain_name
            if (Schema::hasColumn('domains', 'domain') && !Schema::hasColumn('domains', 'domain_name')) {
                $table->renameColumn('domain', 'domain_name');
            }
            
            // Drop added columns
            $columns_to_drop = ['status', 'is_active', 'is_verified', 'api_key', 'verification_token', 'last_checked_at', 'updated_at'];
            foreach ($columns_to_drop as $column) {
                if (Schema::hasColumn('domains', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
