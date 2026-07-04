<?php

namespace App\Models;

use Database\Factories\TestCaseAutomationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $test_case_id
 * @property string $driver
 * @property int $schema_version
 * @property string $status
 * @property array<string, mixed>|null $definition
 * @property string|null $source_hash
 * @property Carbon|null $generated_at
 * @property string|null $last_validated_environment
 * @property Carbon|null $last_validated_at
 * @property string|null $validation_summary
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'test_case_id', 'driver', 'schema_version', 'status', 'definition', 'source_hash',
    'generated_at', 'last_validated_environment', 'last_validated_at', 'validation_summary',
    'approved_by', 'approved_at',
])]
class TestCaseAutomation extends Model
{
    public const DRIVER_PLAYWRIGHT_DSL = 'playwright_dsl';

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_VALIDATING = 'Validating';

    public const STATUS_READY = 'Ready';

    public const STATUS_INVALID = 'Invalid';

    /** @use HasFactory<TestCaseAutomationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'generated_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TestCase, $this> */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<AutomationExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(AutomationExecution::class);
    }
}
