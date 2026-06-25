<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'description', 'original_filename', 'stored_file_path',
        'extracted_text', 'parsed_json', 'status', 'parse_error',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'parsed_json' => 'array'
            ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuestionnaireSection::class)->orderBy('sort_order');
    }

    public function generatedForms(): HasMany
    {
        return $this->hasMany(GeneratedForm::class);
    }
}
