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
        // First, remove any existing duplicates before adding constraints
        $this->removeDuplicates();
        
        Schema::table('content', function (Blueprint $table) {
            // Add a computed column for normalized text (PostgreSQL specific)
            DB::statement('ALTER TABLE content ADD COLUMN normalized_text TEXT GENERATED ALWAYS AS (LOWER(TRIM(REGEXP_REPLACE(text, \'\s+\', \' \', \'g\')))) STORED');
            
            // Add unique constraint on domain_id + normalized_text to prevent content duplicates
            $table->unique(['domain_id', 'normalized_text'], 'content_domain_normalized_text_unique');
            
            // Add index on normalized_text for faster lookups
            $table->index('normalized_text', 'content_normalized_text_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('content_domain_normalized_text_unique');
            
            // Drop the index
            $table->dropIndex('content_normalized_text_index');
        });
        
        // Drop the computed column
        DB::statement('ALTER TABLE content DROP COLUMN IF EXISTS normalized_text');
    }
    
    /**
     * Remove existing duplicates before adding constraints
     */
    private function removeDuplicates(): void
    {
        // Find and remove duplicates based on domain_id and normalized text
        DB::statement("
            DELETE FROM content 
            WHERE id NOT IN (
                SELECT DISTINCT ON (domain_id, LOWER(TRIM(REGEXP_REPLACE(text, '\s+', ' ', 'g')))) id
                FROM content 
                ORDER BY domain_id, LOWER(TRIM(REGEXP_REPLACE(text, '\s+', ' ', 'g'))), created_at ASC
            )
        ");
        
        echo "Removed duplicate content entries based on normalized text.\n";
    }
};
