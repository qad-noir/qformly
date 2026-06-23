<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_question_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['questionnaire_question_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_options');
    }
};
