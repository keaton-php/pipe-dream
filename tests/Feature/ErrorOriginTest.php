<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\NoMethodRule;
use Keaton\PipeDream\Tests\TestCase;
use ReflectionClass;
use stdClass;

/**
 * fatal() and error() records point at the line in the user's rule that
 * raised them, not at the package's PipeRule.
 */
class ErrorOriginTest extends TestCase
{
    /** [file, line] of the first line in a rule's file containing $needle. */
    private function lineOf(string $class, string $needle): array
    {
        $file = (new ReflectionClass($class))->getFileName();

        foreach (file($file) as $number => $line) {
            if (str_contains($line, $needle)) {
                return [$file, $number + 1];
            }
        }

        $this->fail("{$needle} not found in {$file}");
    }

    private function assertRecordPointsAt(array $record, array $origin): void
    {
        [$file, $line] = $origin;

        $this->assertSame($file, $record['context']['file']);
        $this->assertSame($line, $record['context']['line']);
        $this->assertStringEndsWith("[{$file} | line {$line}]", $record['data']);
        $this->assertStringNotContainsString('src/PipeRule.php', $record['data']);
    }

    private function run_(string $rule): PipeEnvelope
    {
        return PipeHub::create()->addPipe($rule)->sendIt(new stdClass());
    }

    public function test_a_fatal_points_at_the_rule_that_called_fatal(): void
    {
        $record = $this->run_(FatalRule::class)->logger()->fatals()->first();

        $this->assertRecordPointsAt($record, $this->lineOf(FatalRule::class, '$this->fatal('));
    }

    public function test_an_error_points_at_the_rule_that_called_error(): void
    {
        $record = $this->run_(ErrorRule::class)->logger()->errors()->first();

        $this->assertRecordPointsAt($record, $this->lineOf(ErrorRule::class, '$this->error('));
    }

    public function test_an_error_made_fatal_keeps_its_origin(): void
    {
        $record = $this->run_(ErrorsAreFatalRule::class)->logger()->fatals()->first();

        $this->assertRecordPointsAt($record, $this->lineOf(ErrorsAreFatalRule::class, '$this->error('));
    }

    public function test_a_missing_run_method_points_at_the_rule_class(): void
    {
        $record = $this->run_(NoMethodRule::class)->logger()->errors()->first();

        $this->assertRecordPointsAt($record, $this->lineOf(NoMethodRule::class, 'class NoMethodRule'));
    }

    public function test_an_exception_thrown_directly_points_at_where_it_was_thrown(): void
    {
        $line = __LINE__ + 2;
        $envelope = PipeHub::create()
            ->addPipe(fn () => throw new PipeRuleFatal('Thrown by hand'))
            ->sendIt(new stdClass());

        $this->assertRecordPointsAt($envelope->logger()->fatals()->first(), [__FILE__, $line]);
    }
}
