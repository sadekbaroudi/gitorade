<?php

// TODO: Move this into its own library and include from the Gitorade project!

namespace Sadekbaroudi\Gitorade\OperationState;

use Sadekbaroudi\Gitorade\OperationState\OperationState;

class OperationStateManager {
    
    protected $operationQueue = array();
    
    protected $executed = array();
    
    public function add(OperationState $operation)
    {
        $this->operationQueue[$operation->getKey()] = $operation;
    }
    
    public function remove(OperationState $operation = NULL)
    {
        if (is_null($operation)) {
            array_pop($this->operationQueue);
            return TRUE;
        } else {
            if ($this->isInQueue($operation)) {
                unset($this->operationQueue[$operation->getKey()]);
                return TRUE;
            }
            return FALSE;
        }
    }
    
    public function isInQueue(OperationState $operation)
    {
        return isset($this->operationQueue[$operation->getKey()]);
    }
    
    public function executeAll()
    {
        $results = array();
        
        foreach ($this->operationQueue as $object) {
            $results[$object->getKey()] = $this->execute($object);
        }
        
        return $results;
    }
    
    public function execute(OperationState $object)
    {
        $result = $object->execute();
        $this->executed[$object->getKey()] = $object;
        $this->remove($object);
        
        return $result;
    }
    
    public function undoAll()
    {
        if (empty($this->executed)) {
            return;
        }
        
        $results = array();
        
        while ($object = array_pop($this->executed)) {
            $results[$object->getKey()] = $this->undo($object);
        }
        
        return $results;
    }
    
    public function undo(OperationState $object)
    {
        return $object->undo();
    }
}