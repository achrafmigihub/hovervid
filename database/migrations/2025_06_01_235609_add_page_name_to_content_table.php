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
            // Add page_name column to track which page the content comes from
            $table->string('page_name', 500)->nullable()->after('url');
            
            // Add index on page_name for faster queries
            $table->index('page_name', 'content_page_name_index');
            
            // Add composite index for domain_id + page_name for domain-specific page queries
            $table->index(['domain_id', 'page_name'], 'content_domain_page_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content', function (Blueprint $table) {
            // Drop the indexes first
            $table->dropIndex('content_page_name_index');
            $table->dropIndex('content_domain_page_index');
            
            // Drop the column
            $table->dropColumn('page_name');
        });
    }
};
