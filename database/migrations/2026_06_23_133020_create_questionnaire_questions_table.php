<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_section_id')->constrained()->cascadeOnDelete();
            $table->string('question_number')->nullable();
            $table->text('title');
            $table->string('type');
            $table->boolean('is_required')->default(true);
            $table->text('help_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['questionnaire_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_questions');
    }
};
