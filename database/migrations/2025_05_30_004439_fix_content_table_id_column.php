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
        Schema::table('content', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['domain_id']);
            $table->dropForeign(['user_id']);
        });
        
        // Drop and recreate the table with proper structure
        Schema::dropIfExists('content');
        
        Schema::create('content', function (Blueprint $table) {
            $table->string('id', 255)->primary(); // Use string for hash-based IDs
            $table->foreignId('domain_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('content_element');
            $table->text('context')->nullable();
            $table->text('url')->nullable();
            $table->text('video_url')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop and recreate with original structure
        Schema::dropIfExists('content');
        
        Schema::create('content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('content_element');
            $table->text('context')->nullable();
            $table->text('url')->nullable();
            $table->text('video_url')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }
};
