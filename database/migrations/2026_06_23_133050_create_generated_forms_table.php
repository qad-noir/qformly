<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('questionnaire_project_id')->constrained()->cascadeOnDelete();
            $table->string('google_form_id')->nullable();
            $table->text('respondent_url')->nullable();
            $table->text('edit_url')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['questionnaire_project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_forms');
    }
};
