<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NightwatchIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An issue Nightwatch reported through its webhook. A row is updated when the
 * same `issue_id` opens again or resolves.
 */
class NightwatchIssue extends Model
{
    /** @use HasFactory<NightwatchIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'ref',
        'type',
        'title',
        'status',
        'priority',
        'url',
        'environment',
        'details',
        'opened_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'ref' => 'integer',
            'details' => 'array',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
