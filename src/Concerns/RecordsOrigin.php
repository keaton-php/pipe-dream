<?php

namespace Keaton\PipeDream\Concerns;

/**
 * Where a rule raised this exception. $this->fatal() and $this->error()
 * construct the exception inside PipeRule, so getFile()/getLine() would
 * point at the package; the origin points at the rule's own call site.
 */
trait RecordsOrigin
{
    protected ?string $originFile = null;

    protected ?int $originLine = null;

    public function setOrigin(string $file, int $line)
    {
        $this->originFile = $file;
        $this->originLine = $line;

        return $this;
    }

    public function originFile(): string
    {
        return $this->originFile ?? $this->getFile();
    }

    public function originLine(): int
    {
        return $this->originLine ?? $this->getLine();
    }
}
