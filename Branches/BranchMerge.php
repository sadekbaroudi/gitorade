<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;

class BranchMerge {
	
    protected $argumentTypeRequirement = 'Sadekbaroudi\Gitorade\Branches\Branch';
    
    protected $branchFrom;
    
    protected $branchTo;
    
    /**
     * If involved in a merge, represent this branch with the following name
     * @var string
     */
    protected $mergeName;
    
    public function __construct($branchFrom, $branchTo)
    {
        $this->setBranchFrom($branchFrom);
    
        $this->setBranchTo($branchTo);
        
        $this->setMergeName($branchFrom->getMergeName() . "_to_" . $branchTo->getMergeName() . "_" . gmdate('YmdHis'));
    }
    
    public function setBranchFrom($branchFrom)
    {
        if (!is_a($branchFrom, $this->argumentTypeRequirement)) {
            throw new GitException(__METHOD__ . ": arg \$branchFrom needs to be a {$this->argumentTypeRequirement}");
        }
        
        $this->branchFrom = $branchFrom;
    }
    
    public function getBranchFrom()
    {
        return $this->branchFrom;
    }
    
    public function setBranchTo($branchTo)
    {
        if (!is_a($branchTo, $this->argumentTypeRequirement)) {
            throw new GitException(__METHOD__ . ": arg \$branchTo needs to be a {$this->argumentTypeRequirement}");
        }
        
        $this->branchTo = $branchTo;
    }
    
    public function getBranchTo()
    {
        return $this->branchTo;
    }
    
    public function setMergeName($string)
    {
        $this->mergeName = $string;
    }
    
    public function getMergeName()
    {
        return $this->mergeName;
    }
    
    public function __toString()
    {
        return $this->getMergeName();
    }
    
    public function merge()
    {
        // CONTINUE HERE
        // NEED TO IMPLEMENT THE MERGE OF THE LOCAL OBJECTS $branchFrom into $branchTo
        // DO I MOVE GITORADE METHODS INTO HERE?
        //   THERE IS A DEPENDENCY ON A LOT OF OBJECTS IN THE GITORADE OBJECT
        // DO I NOT USE THIS METHOD, AND INSTEAD JUST GRAB THE RELEVANT INFO FROM THIS OBJECT TO MERGE?
    }
}