<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'questionnaire_section_id', 'question_number', 'title', 'type',
        'is_required', 'help_text', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSection::class, 'questionnaire_section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionnaireOption::class)->orderBy('sort_order');
    }
}
