<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;

class BranchPullRequest {
	
    protected $branchFrom;
    
    protected $branchTo;
    
    protected $title;
    
    protected $body;
    
    public function __construct(BranchGithub $branchFrom, BranchGithub $branchTo, $title, $body)
    {
        $this->setBranchFrom($branchFrom);
    
        $this->setBranchTo($branchTo);
        
        $this->setTitle($title);
        
        $this->setBody($body);
    }
    
    public function setTitle($title)
    {
        if (!is_string($title)) {
            throw new GitException(__METHOD__ . ": \$title must be a string");
        }
        
        $this->title = $title;
    }
    
    public function getTitle()
    {
        return $this->title;
    }
    
    public function setBody($body)
    {
        if (!is_string($body)) {
            throw new GitException(__METHOD__ . ": \$body must be a string");
        }
    
        $this->body = $body;
    }
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function setBranchFrom(BranchGithub $branchFrom)
    {
        $this->branchFrom = $branchFrom;
    }
    
    public function getBranchFrom()
    {
        return $this->branchFrom;
    }
    
    public function setBranchTo(BranchGithub $branchTo)
    {
        $this->branchTo = $branchTo;
    }
    
    public function getBranchTo()
    {
        return $this->branchTo;
    }
    
    public function canSubmitPullRequest()
    {
        return !empty($this->title) ? TRUE : FALSE;
    }
    
    public function __toString()
    {
        return $this->getBranchFrom()->getUser() . '/' . $this->getBranchFrom()->getBranch() . ' -> ' .
               $this->getBranchTo()->getUser() . '/' . $this->getBranchTo()->getBranch();
    }
}