<?php

namespace Sadekbaroudi\Gitorade\OperationState;

use Sadekbaroudi\Gitorade\OperationState\OperationStateException;

/**
 * This class defines an Operation State. You set actions to execute, and all the
 * appropriate undo actions for those execute actions. You can then use the OperationStateManager
 * class to keep track of what has been executed, and what needs to be undone.
 * 
 * @author sadekbaroudi
 */
class OperationState {
    
    protected $key;
    
    /**
     * @var array An array of arrays, containing the actions to be executed
     */
    protected $executeParameters = array();
    
    /**
     * @var array An array of arrays, containing the actions to undo the executed actions
     */
    protected $undoParameters = array();
    
    /**
     * This will clear the current set of execute actions, and add the passed action
     * 
     * @param Object $object the object on which you will be executing that action, NULL if a method without a class
     * @param string $method the method that you would like to call
     * @param array $arguments an array of arguments to be passed to the method
     * @return \Sadekbaroudi\Gitorade\OperationState\OperationState
     */
    public function setExecute($object, $method, $arguments = array())
    {
        $this->clearExecute();
    
        $this->addExecute($object, $method, $arguments);
    
        return $this;
    }
    
    /**
     * This will add the passed action to the list of execute actions to be called
     * 
     * @param Object $object the object on which you will be executing that action, NULL if a method without a class
     * @param string $method the method that you would like to call
     * @param array $arguments an array of arguments to be passed to the method
     * @return \Sadekbaroudi\Gitorade\OperationState\OperationState
     */
    public function addExecute($object, $method, $arguments = array())
    {
        $this->executeParameters[] = array(
        	'object' => $object,
            'method' => $method,
            'arguments' => $arguments,
        );
        
        return $this;
    }
    
    /**
     * This will clear all the execute actions from the queue
     */
    protected function clearExecute()
    {
        $this->executeParameters = array();
    }
    
    /**
     * This will execute all the actions in the queue. It will not clear the queue!
     * 
     * @return array returns the results of the executed actions, with OperationState->getKey() as the array keys
     */
    public function execute()
    {
        $returnValues = array();
        
        while ($execute = array_pop($this->executeParameters)) {
            $returnValues[] = $this->run($execute);
        }
        
        return $returnValues;
    }
    
    /**
     * This will clear the current set of undo actions, and add the passed action
     *
     * @param Object $object the object on which you will be executing that action, NULL if a method without a class
     * @param string $method the method that you would like to call
     * @param array $arguments an array of arguments to be passed to the method
     * @return \Sadekbaroudi\Gitorade\OperationState\OperationState
     */
    public function setUndo($object, $method, $arguments = array())
    {
        $this->clearUndo();
    
        $this->addUndo($object, $method, $arguments);
    
        return $this;
    }
    
    /**
     * This will add the passed action to the list of undo actions to be called
     *
     * @param Object $object the object on which you will be executing that action, NULL if a method without a class
     * @param string $method the method that you would like to call
     * @param array $arguments an array of arguments to be passed to the method
     * @return \Sadekbaroudi\Gitorade\OperationState\OperationState
     */
    public function addUndo($object, $method, $arguments = array())
    {
        $this->undoParameters[] = array(
            'object' => $object,
            'method' => $method,
            'arguments' => $arguments,
        );
        
        return $this;
    }
    
    /**
     * This will clear all the execute actions from the queue
     */
    protected function clearUndo()
    {
        $this->undoParameters = array();
    }
    
    /**
     * This will execute all the undo actions in the queue. It will not clear the queue!
     *
     * @return array returns the results of the undo actions, with OperationState->getKey() as the array keys
     */
    public function undo()
    {
        $returnValues = array();
        while ($undo = array_pop($this->undoParameters)) {
            $returnValues[] = $this->run($undo);
        }
        
        return $returnValues;
    }
    
    /**
     * This will run the action passed, whether execute or undo.
     * 
     * @param array $params array in the format array('object' => $object, 'method' => 'methodName', 'arguments' => array());
     * @throws \RuntimeException
     * @return mixed returns the result of the method call
     */
    protected function run($params)
    {
        if (is_null($params['object'])) {
        
            if (!function_exists($params['method'])) {
                throw new OperationStateException("Method {$params['method']} does not exist.");
            }
        
            return call_user_func_array($params['method'], $params['arguments']);
        
        } elseif (is_object($params['object'])) {
        
            if (!method_exists($params['object'], $params['method'])) {
                throw new OperationStateException("Method {$params['method']} does not exist on object " . get_class($params['object']));
            }
        
            return call_user_method_array($params['method'], $params['object'], $params['arguments']);
        
        } else {
            throw new OperationStateException("\$params['object'] is not a valid object");
        }
    }
    
    /**
     * Get the a unique key for this object. It should return the same value regardless of when this function is called!
     * 
     * @return string
     */
    public function getKey()
    {
        if (!isset($this->key)) {
            $this->key = md5(microtime().rand());
        }
        return $this->key;
    }
}