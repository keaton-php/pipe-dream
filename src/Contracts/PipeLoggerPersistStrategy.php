<?php

namespace Keaton\PipeDream\Contracts;

use Keaton\PipeDream\PipeEnvelope;

/**
 * Stores an envelope's log somewhere (a database row, a file, a queue).
 * Called once per flow by PipeManager, or once per run by a standalone hub.
 */
interface PipeLoggerPersistStrategy
{
    public function persist(PipeEnvelope $subject);
}
