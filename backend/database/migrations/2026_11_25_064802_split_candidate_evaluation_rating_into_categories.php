<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An evaluation is three 1 to 5 grades (knowledge of the position, appearance, experience in the position), and
     * `rating` becomes their average, kept to two decimals. Evaluations given before keep their single grade as
     * `rating`, with no grades for the three categories.
     */
    public function up(): void
    {
        Schema::table('candidate_evaluations', function (Blueprint $table) {
            $table->unsignedTinyInteger('knowledge_rating')->nullable()->after('user_id');
            $table->unsignedTinyInteger('appearance_rating')->nullable()->after('knowledge_rating');
            $table->unsignedTinyInteger('experience_rating')->nullable()->after('appearance_rating');
            $table->decimal('rating', 3, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_evaluations', function (Blueprint $table) {
            $table->dropColumn(['knowledge_rating', 'appearance_rating', 'experience_rating']);
            $table->unsignedTinyInteger('rating')->change();
        });
    }
};
