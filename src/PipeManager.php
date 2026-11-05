<?php
namespace R1kon\PipeDream;


use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\Exceptions\PipeRuleLoadView;
use R1kon\PipeDream\Exceptions\PipeRuleRedirect;
use R1kon\PipeDream\Exceptions\PipeRuleFatal;
use R1kon\PipeDream\Exceptions\PipeHubException;

class PipeManager 
{
    protected $hubs = [];
    protected $subject;
    protected $name;
    protected $description;
    protected ?\Closure $thenCallback = null;


    public function __construct()
    {
        
    }

    public static function create($hubs = null)
    {
        return (new static)->addHubs($hubs);
    }

    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    public function description($description)
    {
        $this->description = $description;
        return $this;
    }

    public function hubs()
    {
        return $this->hubs;
    }

    public function addHub($hub)
    {
        if($hub instanceof PipeHub)
            $this->hubs[] = $hub;
        
        return $this;
    }

    public function addHubs(array $hubs)
    {
        array_map(function($hub) {
            $this->addHub($hub);
        }, $hubs);
       
        return $this;
    }

    public function subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function sendIt()
    {
        
        $this->subject = PipeEnvelope::wrap($this->subject);

        $this->subject
                ->logger()
                ->info("PipeManager:sendIt","Sending subject through all hubs", 
                        $this->getPipeManagerContext()
                );
 
        $exceptionThrown = false;

        try {
            foreach($this->hubs as $hub)
            {
                $this->subject = $hub->subject($this->subject)->sendIt();
            }
        } catch(PipeRuleLoadView $e) {
            throw $e;
        } catch(PipeRuleRedirect $e) {
            throw $e;
        } catch(\Exception $e) {
            $this->handleException($e);
            $exceptionThrown = true;
        }

        if(!$exceptionThrown)
                $this->callback($this->thenCallback, [
                    'subject' => $this->subject
                ]);
            
        $this->subject->logger()->info("PipeManager:sendIt","Finalized result!");

        return $this->subject;
    }

    public function then(\Closure $callback)
    {
        $this->thenCallback = $callback;

        return $this;
    }

    public function callback(?\Closure $callback = null, $parameters = [])
    {
        return $callback ? $this->callViaContainer($callback, $parameters) : null;
    }

    private function callViaContainer($callback, $parameters = [], $app = null)
    {
        $app ??= app();
        return $app->call($callback, $parameters);
    }

    private function getPipeManagerContext()
    {
        return [
            'name' => $this->name,
            'description' => $this->description
        ];
    }

    private function handleException($e)
    {
        $this->subject->logger()->error("PipeManager:sendIt",$e->getMessage());
    }

}