<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use App\Support\OptionTextAnalyser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id', 'option_key', 'option_text', 'option_image_path',
        'is_correct', 'display_order', 'pin_last',
    ];

    /** is_correct never reaches a student payload. */
    protected $hidden = ['is_correct'];

    protected function casts(): array
    {
        return [
            'option_key' => OptionKey::class,
            'is_correct' => 'boolean',
            'pin_last' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Shuffle-safety checks, delegated to App\Support\OptionTextAnalyser so the
     * patterns live in one auditable place and are shared with the import pipeline.
     */
    public static function textReferencesOtherOptions(string $text): bool
    {
        return OptionTextAnalyser::referencesOtherOptions($text);
    }

    public static function isAllOrNoneOfTheAbove(string $text): bool
    {
        return OptionTextAnalyser::shouldPinLast($text);
    }
}
