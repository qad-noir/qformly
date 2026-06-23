<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireOption extends Model
{
    use HasFactory;

    protected $fillable = ['questionnaire_question_id', 'label', 'value', 'sort_order'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireQuestion::class, 'questionnaire_question_id');
    }
}
