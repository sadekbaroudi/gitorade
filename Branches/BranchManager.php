<?php

namespace Sadekbaroudi\Gitorade\Branches;

use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Branches\Branch;
use Sadekbaroudi\Gitorade\Branches\BranchLocal;
use Sadekbaroudi\Gitorade\Branches\BranchRemote;

class BranchManager {
    
    /**
     * This is the array of branch objects stored in this collection
     * @var array
     */
    protected $branches;
    
    /**
     * Add a branch object to the collection of branches
     * 
     * @param Gitorade\Branches\Branch $branch
     */
    public function addByObject($branchObject)
    {
        if (!is_object($branchObject) || !is_subclass_of($branchObject, 'Gitorade\Branches\Branch')) {
            throw new GitException('Invalid branch added to Branches object');
        }
        
        if ($this->exists($branchObject)) {
            return;
        }
        
        $this->branches[$this->getKey($branchObject)] = $branchObject;
    }
	
    public function getBranchObjectByName($branchName)
    {
        if (strpos($branchName, 'remotes/') !== FALSE) {
            return new BranchRemote($branchName);
        } else {
            return new BranchLocal($branchName);
        }
    }
    
    /**
     * 
     * @param Sadekbaroudi\Gitorade\Branches\Branch $branchObject
     */
    public function set($branchObject)
    {
        $this->branches[(string)$branchObject] = $branchObject;
    }
    
    /**
     * Add a branch to the branch collection
     * 
     * @param string $branchName
     */
    public function add($branchName)
    {
        if ($this->exists($branchName)) {
            return;
        }
        
        $this->branches[(string)$branchName] = $this->getBranchObjectByName($branchName);
    }
    
    /**
     * Get a branch from the branch collection. NOTE: If it doesn't exist, it will be added
     *
     * @param string $branchName
     */
    public function get($branchName)
    {
        if ($this->exists($branchName) == FALSE) {
            $this->add($branchName);
        }
        
        return $this->branches[(string)$branchName];
    }
    
    /**
     * Remove a branch from the branch collection
     *
     * @param string $branchName
     */
    public function remove($branchName)
    {
        if ($this->exists($branchName)) {
            unset($this->branches[(string)$branchName]);
        }
    }
    
    /**
     * Check if a branch exists in the collection
     *
     * @param string $branchName
     */
    public function exists($branchName)
    {
        return !empty($this->branches[(string)$branchName]);
    }    
}