<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Branches\Branch;
use Sadekbaroudi\Gitorade\Branches\BranchRemote;

class BranchLocal extends Branch {
	
    protected $remote;
    
    public function __construct($branch, $remote = NULL)
    {
        if (strpos($branch, '/') !== FALSE) {
            throw new GitException("BranchLocal '{$branch}' cannot contain a /");
        }
        
        if (!is_null($remote)) {
            $this->remote = new BranchRemote($remote);
        }
        
        parent::__construct($branch);
    }
    
    public function getType()
    {
        return 'local';
    }
}