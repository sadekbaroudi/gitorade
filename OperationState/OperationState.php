<?php

namespace Sadekbaroudi\Gitorade\OperationState;

/**
 * This class defines
 * 
 * @author sadekbaroudi
 */
class OperationState {
    
    protected $executeParameters = array();
    
    protected $undoParameters = array();
    
    public function setExecute($object, $method, $arguments = array())
    {
        $this->clearExecute();
    
        $this->addExecute($object, $method, $arguments);
    
        return $this;
    }
    
    public function addExecute($object, $method, $arguments = array())
    {
        $this->executeParameters[] = array(
        	'object' => $object,
            'method' => $method,
            'arguments' => $arguments,
        );
        
        return $this;
    }
    
    protected function clearExecute()
    {
        $this->executeParameters = array();
    }
    
    public function execute()
    {
        $returnValues = array();
        
        while ($execute = array_pop($this->executeParameters)) {
            $returnValues[] = $this->run($execute);
        }
        
        return $returnValues;
    }

    public function setUndo($object, $method, $arguments = array())
    {
        $this->clearUndo();
    
        $this->addUndo($object, $method, $arguments);
    
        return $this;
    }
    
    public function addUndo($object, $method, $arguments = array())
    {
        $this->undoParameters[] = array(
            'object' => $object,
            'method' => $method,
            'arguments' => $arguments,
        );
        
        return $this;
    }
    
    protected function clearUndo()
    {
        $this->undoParameters = array();
    }
    
    public function undo()
    {
        $returnValues = array();
        while ($undo = array_pop($this->undoParameters)) {
            $returnValues[] = $this->run($undo);
        }
        
        return $returnValues;
    }
    
    protected function run($params)
    {
        if (is_null($params['object'])) {
        
            if (!function_exists($params['method'])) {
                throw new \RuntimeException("Method {$params['method']} does not exist.");
            }
        
            return call_user_func_array($params['method'], $params['arguments']);
        
        } elseif (is_object($params['object'])) {
        
            if (!method_exists($params['object'], $params['method'])) {
                throw new \RuntimeException("Method {$params['method']} does not exist on object " . get_class($params['object']));
            }
        
            return call_user_method_array($params['method'], $params['object'], $params['arguments']);
        
        } else {
            throw new \RuntimeException("\$params['object'] is not a valid object");
        }
    }
    
    public function getKey()
    {
        return md5(serialize($this));
    }
}