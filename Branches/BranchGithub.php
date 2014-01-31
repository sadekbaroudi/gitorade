<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;

class BranchGithub {
	
    protected $user;
    
    protected $repo;
    
    protected $branch;
    
    public function __construct($user, $repo, $branch)
    {
        if (!is_string($user)) {
            throw new GitException(__METHOD__ . ": \$user must be a string");
        }
        
        if (!is_string($repo)) {
            throw new GitException(__METHOD__ . ": \$repo must be a string");
        }
        
        if (!is_string($branch)) {
            throw new GitException(__METHOD__ . ": \$branch must be a string");
        }
        
        $this->setUser($user);
        $this->setRepo($repo);
        $this->setBranch($branch);
    }
    
    protected function setUser($user)
    {
        $this->user = $user;
    }
    
    public function getUser()
    {
        return $this->user;
    }
    
    protected function setRepo($repo)
    {
        $this->repo = $repo;
    }
    
    public function getRepo()
    {
        return $this->repo;
    }
    
    protected function setBranch($branch)
    {
        $this->branch = $branch;
    }
    
    public function getBranch()
    {
        return $this->branch;
    }
}