<?php

namespace R1kon\PipeDream\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use R1kon\PipeDream\Contracts\PipeLoggerStrategy;
use R1kon\PipeDream\PipeLogger;
use R1kon\PipeDream\Strategies\PipeLoggerJsonStrategy;

/**
 * One persisted run of a flow (see DatabasePersistStrategy).
 *
 * @property int $id
 * @property string $run_id
 * @property string|null $flow
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property string $log
 * @property bool $has_errors
 * @property bool $has_fatals
 */
class PipeDreamLog extends Model
{
    protected $table = 'pipe_dream_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'has_errors' => 'boolean',
        'has_fatals' => 'boolean',
    ];

    /**
     * The model the flow ran for, when the subject was an Eloquent model.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The stored log rebuilt into a PipeLogger, ready for a timeline view.
     * Pass the strategy the log was written with if it was not JSON.
     */
    public function logger(?PipeLoggerStrategy $strategy = null): PipeLogger
    {
        return (new PipeLogger())->rebuild($this->log, $strategy ?? new PipeLoggerJsonStrategy());
    }

    public function scopeForRun($query, string $runId)
    {
        return $query->where('run_id', $runId);
    }

    public function scopeWithProblems($query)
    {
        return $query->where(fn ($q) => $q->where('has_errors', true)->orWhere('has_fatals', true));
    }
}
