<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'questionnaire_project_id', 'google_form_id', 'respondent_url',
        'edit_url', 'status', 'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireProject::class, 'questionnaire_project_id');
    }
}
