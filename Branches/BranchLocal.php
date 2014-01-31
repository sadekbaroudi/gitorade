<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Branches\Branch;
use Sadekbaroudi\Gitorade\Branches\BranchRemote;

class BranchLocal extends Branch {
	
    /**
     * Can be null if not set, but is the remote representation of this local branch.
     * 
     * @var Sadekbaroud\Gitorade\Branches\BranchRemote
     */
    protected $remote;
    
    /**
     * Instantiate a new local git branch object
     * 
     * @param string $branch
     * @param Sadekbaroud\Gitorade\Branches\BranchRemote $remote The remote branch representation of this local branch.
     *                                                           Used only if you need to push this local branch to remote.
     * @throws GitException
     */
    public function __construct($branch, $remote = NULL)
    {
        if (strpos($branch, '/') !== FALSE) {
            throw new GitException("BranchLocal '{$branch}' cannot contain a /");
        }
        
        if (!is_null($remote)) {
            $this->setRemote($remote);
        }
        
        parent::__construct($branch);
    }
    
    /**
     * Set the remote branch for this local branch
     * 
     * @param Sadekbaroud\Gitorade\Branches\BranchRemote $remote The remote branch representation of this local branch
     */
    public function setRemote(BranchRemote $remote)
    {
        $this->remote = $remote;
    }
    
    /**
     * Get the remote branch for this local branch
     * 
     * @return Sadekbaroud\Gitorade\Branches\BranchRemote The remote branch representation of this local branch
     */
    public function getRemote()
    {
        return $this->remote;
    }
    
    /**
     * Check to see if this local branch has a remote branch
     * 
     * @return boolean does this local branch have a remote branch representation
     */
    public function hasRemote()
    {
        $remote = $this->getRemote();
        return is_null($remote) ? FALSE : TRUE;
    }
    
    /**
     * (non-PHPdoc)
     * @see \Sadekbaroudi\Gitorade\Branches\Branch::getType()
     */
    public function getType()
    {
        return 'local';
    }
}