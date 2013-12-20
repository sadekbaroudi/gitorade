<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Branches\Branch;
use Sadekbaroudi\Gitorade\Branches\BranchRemote;

class BranchPullRequest extends BranchRemote {
	
    protected $branchFrom;
    
    protected $branchTo;
    
    public function setFrom($branchFrom) {
        $this->branchFrom = $branchFrom;
    }
    
    public function setTo($branchTo) {
        $this->branchTo = $branchTo;
    }
    
    public function getFrom() {
        return $this->branchFrom;
    }
    
    public function getTo() {
        return $this->branchTo;
    }
    
    public function canSubmitPullRequest() {
        return !is_null($this->getFrom()) && !is_null($this->getTo());
    }
}