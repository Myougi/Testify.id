<?php

namespace App\Models;

use Database\Factories\TestCaseStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $test_case_id
 * @property int $position
 * @property string $action
 * @property string $expected_result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'test_case_id',
    'position',
    'action',
    'expected_result',
])]
class TestCaseStep extends Model
{
    /** @use HasFactory<TestCaseStepFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestCase, $this>
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }
}
