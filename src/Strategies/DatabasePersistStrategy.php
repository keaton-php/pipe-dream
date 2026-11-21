<?php

namespace Keaton\PipeDream\Strategies;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Contracts\PipeLoggerStrategy;
use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;

/**
 * Stores each run as one row in pipe_dream_logs, keyed by the envelope's
 * run id: persisting the same envelope again updates that row, atomically.
 */
class DatabasePersistStrategy implements PipeLoggerPersistStrategy
{
    protected PipeLoggerStrategy $format;

    /**
     * @param  PipeLoggerStrategy|null  $format  how the log column is encoded (JSON by default)
     * @param  class-string<PipeDreamLog>  $model
     */
    public function __construct(?PipeLoggerStrategy $format = null, protected string $model = PipeDreamLog::class)
    {
        if (! is_a($model, PipeDreamLog::class, true)) {
            throw new InvalidArgumentException("[{$model}] must extend ".PipeDreamLog::class.'.');
        }

        $this->format = $format ?? new PipeLoggerJsonStrategy();
    }

    /**
     * One atomic INSERT ... ON CONFLICT (run_id) DO UPDATE, so two workers
     * persisting the same run can never race into a duplicate-key error.
     * created_at is set on insert only; updated_at on every write.
     */
    public function persist(PipeEnvelope $subject)
    {
        $model = $subject->subject();
        $hasSubject = $model instanceof Model && $model->getKey() !== null;

        $row = [
            'run_id' => $subject->runId(),
            'flow' => $subject->flowName(),
            'subject_type' => $hasSubject ? $model->getMorphClass() : null,
            'subject_id' => $hasSubject ? (string) $model->getKey() : null,
            'log' => $this->format->output($subject->logs()),
            'has_errors' => $subject->hasErrors(),
            'has_fatals' => $subject->hasFatalErrors(),
        ];

        $query = $this->model::query();

        // Eloquent adds created_at and updated_at to the inserted row, and
        // updated_at (never created_at) to the columns updated on conflict.
        $query->upsert([$row], ['run_id'], array_values(array_diff(array_keys($row), ['run_id'])));

        return $this->model::query()->where('run_id', $subject->runId())->first();
    }
}
