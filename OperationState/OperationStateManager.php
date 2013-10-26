<?php

// TODO: Move this into its own library and include from the Gitorade project!

namespace Sadekbaroudi\Gitorade\OperationState;

use Sadekbaroudi\Gitorade\OperationState\OperationState;

class OperationStateManager {
    
    protected $operationQueue = array();
    
    protected $executed = array();
    
    public function add(OperationState $operation)
    {
        $this->operationQueue[$this->getKey($operation)] = $operation;
    }
    
    public function remove(OperationState $operation = NULL)
    {
        if (is_null($operation)) {
            array_pop($this->operationQueue);
        } else {
            if ($this->isInQueue($operation)) {
                unset($this->operationQueue[$this->getKey($operation)]);
            }
        }
    }
    
    public function isInQueue(OperationState $operation)
    {
        return isset($this->operationQueue[$this->getKey($operation)]);
    }
    
    public function executeAll()
    {
        foreach ($this->operationQueue as $object) {
            $this->execute($object);
        }
    }
    
    public function execute(OperationState $object)
    {
        $object->execute();
        $this->executed[$this->getKey($object)] = $object;
        $this->remove($object);
    }
    
    public function undoAll()
    {
        if (empty($this->executed)) {
            return;
        }
        
        while ($object = array_pop($this->executed)) {
            $this->undo($object);
        }
    }
    
    public function undo(OperationState $object)
    {
        $object->undo();
    }
    
    protected function getKey($object)
    {
        return md5(serialize($object));        
    }
}