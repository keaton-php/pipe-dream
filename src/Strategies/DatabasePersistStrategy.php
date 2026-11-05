<?php

namespace R1kon\PipeDream\Strategies;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use R1kon\PipeDream\Contracts\PipeLoggerPersistStrategy;
use R1kon\PipeDream\Contracts\PipeLoggerStrategy;
use R1kon\PipeDream\Models\PipeDreamLog;
use R1kon\PipeDream\PipeEnvelope;

/**
 * Stores each run as one row in pipe_dream_logs, keyed by the envelope's
 * run id: persisting the same envelope again updates that row.
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

    public function persist(PipeEnvelope $subject)
    {
        $attributes = [
            'flow' => $subject->flowName(),
            'log' => $this->format->output($subject->logs()),
            'has_errors' => $subject->hasErrors(),
            'has_fatals' => $subject->hasFatalErrors(),
        ];

        $model = $subject->subject();

        if ($model instanceof Model && $model->getKey() !== null) {
            $attributes['subject_type'] = $model->getMorphClass();
            $attributes['subject_id'] = $model->getKey();
        }

        return $this->model::query()->updateOrCreate(['run_id' => $subject->runId()], $attributes);
    }
}
