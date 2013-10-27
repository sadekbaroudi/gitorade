<?php

// TODO: Move this into its own library and include from the Gitorade project!

namespace Sadekbaroudi\Gitorade\OperationState;

use Sadekbaroudi\Gitorade\OperationState\OperationState;
use Sadekbaroudi\Gitorade\OperationState\OperationStateException;

/**
 * This class will manage a list of operation objects in a queue. You can add and remove objects,
 * execute actions/undo (one or all).
 * 
 * @author sadekbaroudi
 */
class OperationStateManager {
    
    /**
     * Holds an array of OperationState objects to be executed
     * @var array
     */
    protected $operationQueue = array();
    
    /**
     * Once OperationState objects are executed, they go into this queue, mostly used to handle undo
     * @var array
     */
    protected $executed = array();
    
    /**
     * Add an object to the queue to be executed
     * 
     * @param OperationState $operation
     */
    public function add(OperationState $operation)
    {
        $this->operationQueue[$operation->getKey()] = $operation;
    }
    
    /**
     * Remove an object from the execution queue
     * 
     * @param OperationState $operation
     * @return boolean returns true if the object was in the queue and was removed, false if it wasn't there
     */
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
    
    /**
     * Check if the passed object is already in the queue
     * 
     * @param OperationState $operation
     * @return boolean returns true if was the object in the queue, false otherwise
     */
    public function isInQueue(OperationState $operation)
    {
        return isset($this->operationQueue[$operation->getKey()]);
    }
    
    /**
     * Execute all the OperationState objects, and return the results of the calls
     * 
     * @return array returns an array that contains all the results of the executions, keyed by the object->getKey()
     */
    public function executeAll()
    {
        $results = array();
        
        foreach ($this->operationQueue as $object) {
            $results[$object->getKey()] = $this->execute($object);
        }
        
        return $results;
    }
    
    /**
     * This will execute the passed operation state object. Note that it must be the original OperationState
     * object that was passed in to be added to the queue, otherwise the $object->getKey() will return
     * a different value, and will break the OperationStateManager
     * 
     * @param OperationState $object
     * @return mixed returns the result of the OperationState call(s)
     */
    public function execute(OperationState $object)
    {
        if (!$this->isInQueue($object)) {
            throw new OperationStateException("Object passed to execute() not in the queue, likely missing call to add()");
        }
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