<?php

namespace R1kon\PipeDream;

use R1kon\PipeDream\Exceptions\PipeRuleLoadView;
use Illuminate\Support\Str;
use R1kon\PipeDream\PipeRuleResult;
use R1kon\PipeDream\Exceptions\PipeRuleRedirect;
use R1kon\PipeDream\Exceptions\PipeRuleError;
use R1kon\PipeDream\Exceptions\PipeRuleExceptionHandler;
use R1kon\PipeDream\Exceptions\PipeRuleFatal;


abstract class PipeRule
{
    const ERROR = 'error';
    const FATAL = 'fatal';

    public const SUCCESS = 1;
    public const FAIL = 2;
    public const FATAL_FAIL = 3;
    public const SKIP = 4;

    public static $name = 'PipeRule';
    public static $description = "New base pipe rule";
    public static $runFunction = null;
    public static $saveResultAs = null;

    protected $defaultResult = self::SUCCESS;

    protected $rethrowErrors = false;
    protected $errorsAreFatal = false;
    protected $stopOnError = false;
    protected $shouldSkip = false;
    
    protected $subject;

    public function __construct($errorsAreFatal = false, $stopOnError = false, $shouldSkip = false)
    {
        $this->stopOnError = $stopOnError;
        $this->errorsAreFatal = $errorsAreFatal;
        $this->shouldSkip = $shouldSkip;
    }

    public function shouldSkip($skip = true)
    {
        $this->shouldSkip = $skip;
    }

    public function getExtendedClassMethods()
    {
        $extendedClass = get_called_class();
        $reflectionClass = new \ReflectionClass($extendedClass);
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        $extendedClassMethods = [];
        foreach ($methods as $method) {
            if ($method->class === $extendedClass) {
                $extendedClassMethods[] = $method->name;
            }
        }

        return $extendedClassMethods;
    }

    private function getExecuteFunctionName()
    {
        //Try what's given to us first
        if(is_callable([$this, static::$runFunction]))
            return static::$runFunction;
        
        //Try to find it based on class name
        if($method = collect($this->getExtendedClassMethods())
            ->filter(fn($method) => Str::upper($method) === Str::upper(class_basename($this)))
            ->first())
                return $method;

        return null;
    }

    protected function runFromContainer($method, $parameters = [])
    {
        if(method_exists($this, $method))
            return app()->call([$this, $method], $parameters);
        
        return null;
       // throw new \BadMethodCallException("Method {$method} does not exist in " . get_class($this));
    }

    public function execute($subject)
    {
        if($this->shouldSkip)
        {
            $this->skip("Skipping rule");
            return $subject;
        }

        if(!$method = $this->getExecuteFunctionName())
            $this->error("No execute function found - skipping");

        $result = app()->call([$this, $method], [
            'subject' => $subject
        ]) ?? $this->defaultResult;
        
        $this->logExecutionResult($subject, $this->ruleClass(), $result);

        return $subject;
    }

    protected function logExecutionResult($subject, $key, $result)
    {
        $subject->saveRuleResult(new PipeRuleResult($this->ruleClass(), $result));
        $subject->set($key, $result);
    }

    public function handle($subject, \Closure $next)
    {
        $this->register($subject);

        try {
            //execute the rule
            $subject = $this->execute($subject);
        } catch (PipeRuleError $e) {
            $this->registerException($e);
            if($this->stopOnError)
                throw $e;

        } catch(PipeRuleFatal $e) {
            $this->registerException($e, self::FATAL);
            throw $e;

        } catch(PipeRuleRedirect $e) {
            throw $e; //just rethrow it

        } catch(PipeRuleLoadView $e) {
            throw $e; //just rethrow it

        } catch (\Exception $e) {

            $this->registerException($e);
            $this->logExecutionResult($this->subject, $this->ruleClass(), self::FAIL);
            
            if($this->rethrowErrors)
                throw $e;

        } finally {
            $this->unregister();
        }

        return $next($subject);
    }

    protected function ruleClass()
    {
        return get_class($this);
    }

    protected function withoutErrorHandling()
    {
        $this->rethrowErrors = true;
    }

    protected function registerException($exception, $type = self::ERROR)
    {
        $this->subject->registerException($exception, $type, $this->ruleClass());

       // $this->log($type, $message);
    }

    protected function register($subject)
    {
        $this->subject = $subject;
        $this->subject->register($this);
    }

    protected function unregister()
    {
        $this->subject->unregister($this);
    }

    protected function info($message)
    {
        $this->log('info', $message);
    }

    protected function fatal($message)
    {
        $this->logExecutionResult($this->subject, $this->ruleClass(), self::FATAL_FAIL);
        throw new PipeRuleFatal($message, $this->subject);
    }

    protected function error($message)
    {
        $this->logExecutionResult($this->subject, $this->ruleClass(), self::FAIL);
        throw new PipeRuleError($message, $this->subject);
    }

    protected function skip($message)
    {
        $this->logExecutionResult($this->subject, $this->ruleClass(), self::SKIP);
    }

    protected function warning($message)
    {
        $this->log('warning', $message);
    }

    protected function log($level, $message)
    {
        $this->subject->logger()->{$level}($this->ruleClass(), $message);
    }

    public function __set($name, $value)
    {
        $this->subject->set($name, $value);
    }

    public function __get($name)
    {
        return $this->subject->get($name);
    }

    public function __call($method, $arguments)
    {
        if (preg_match('/^(set|get)([A-Za-z]+)Result$/', $method, $matches)) {
            $action = $matches[1];
            $key = strtolower(preg_replace('/\B([A-Z])/', '_$1', $matches[2]));

            if ($action === 'set') {
                return $this->subject->session()->set($key, $arguments[0]);
            } elseif ($action === 'get') {
                return $this->subject->session()->get($key);
            }
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

}