<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Support\SupportChatCounter;
use Illuminate\Database\Eloquent\Model;

/**
 * One day of Support chat events, keyed by its UTC date. A count only: no
 * Account, User or message content, and no relation that could cascade away.
 */
class SupportChatDailyCounter extends Model
{
    protected $primaryKey = 'date';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'conversations_started',
        'user_messages',
        'handoffs',
        'feedback_up',
        'feedback_down',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'list_cost_micros',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'conversations_started' => 'integer',
            'user_messages' => 'integer',
            'handoffs' => 'integer',
            'feedback_up' => 'integer',
            'feedback_down' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'list_cost_micros' => 'integer',
        ];
    }

    public function add(SupportChatCounter $counter, int $amount = 1): void
    {
        $this->increment($counter->value, $amount);
    }
}
