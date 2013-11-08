<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Branches\Branch;

class BranchRemote extends Branch {
	
    protected $prefix = 'remotes';
    
    protected $alias;
    
    protected $branchOnly;
    
    public function __construct($branch)
    {
        if (!is_string($branch)) {
            throw new GitException("Branch Objects must be instantiated with a string, " . gettype($branch) . " passed.");
        }
        
        if (strpos($branch, "{$this->prefix}/") !== 0) {
            throw new GitException("The BranchRemote must begin with '{$this->prefix}/'");
        }
        
        $exploded = explode('/', $branch, 3);
        
        if (count($exploded) < 3) {
            throw new GitException("The BranchRemote name '{$branch}' must be in the format remotes/alias/branchname");
        }
        
        // Set up the local variables
        $this->alias = $exploded[1];
        $this->branchOnly = $exploded[2];
        $this->mergeName = $this->branchOnly;
        
        parent::__construct($branch);
    }
    
    public function getBranch()
    {
        return $this->branchOnly;
    }
    
    public function getAlias()
    {
        return $this->alias;
    }
    
    public function getType()
    {
        return 'remote';
    }
}