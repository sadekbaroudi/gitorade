<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;

abstract class Branch {
	
    protected $branch;
    
    /**
     * If involved in a merge, represent this branch with the following name
     * @var string
     */
    protected $mergeName;
    
    public function __construct($branch)
    {
        if (!is_string($branch)) {
            throw new GitException("Branch Objects must be instantiated with a string, " . gettype($branch) . " passed.");
        }
        
                
        $this->branch = $branch;
        $this->mergeName = $branch;
    }
    
    public function setMergeName($string)
    {
        $this->mergeName = $string;
    }
    
    public function getMergeName()
    {
        return $this->mergeName;
    }
    
    public function getBranch()
    {
        return $this->branch;
    }
    
    public function fullBranchString()
    {
        return $this->branch;
    }
    
    public function __toString()
    {
        return $this->branch;
    }
    
    abstract public function getType();
}