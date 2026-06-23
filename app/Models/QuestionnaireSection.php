<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireSection extends Model
{
    use HasFactory;

    protected $fillable = ['questionnaire_project_id', 'title', 'help_text', 'sort_order'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireProject::class, 'questionnaire_project_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionnaireQuestion::class)->orderBy('sort_order');
    }
}
