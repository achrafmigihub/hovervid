<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add foreign key constraint for sessions table
        try {
            Schema::table('sessions', function (Blueprint $table) {
                // First check if the column exists and is a foreign key
                if (Schema::hasColumn('sessions', 'user_id')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                }
            });
        } catch (\Exception $e) {
            // Foreign key might already exist, just continue
        }

        // Update subscriptions table
        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'user_id')) {
                    // Create the foreign key
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                }
            });
        } catch (\Exception $e) {
            // Foreign key might already exist, just continue
        }

        // Update payments table
        try {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'user_id')) {
                    // Create the foreign key
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                }
            });
        } catch (\Exception $e) {
            // Foreign key might already exist, just continue
        }

        // Create indexes on foreign key columns for better join performance
        try {
            Schema::table('sessions', function (Blueprint $table) {
                if (Schema::hasColumn('sessions', 'user_id')) {
                    $table->index('user_id');
                }
            });
        } catch (\Exception $e) {
            // Index might already exist, just continue
        }

        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'user_id')) {
                    $table->index('user_id');
                }
            });
        } catch (\Exception $e) {
            // Index might already exist, just continue
        }

        try {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'user_id')) {
                    $table->index('user_id');
                }
            });
        } catch (\Exception $e) {
            // Index might already exist, just continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We won't drop the foreign keys in the down method
        // because they might have existed before this migration
    }
}; 