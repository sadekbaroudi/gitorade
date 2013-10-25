<?php

namespace Sadekbaroudi\Gitorade;

use GitWrapper\GitException;

class Gitorade {
    
    protected $config;
    
    protected $gitConfig;
    
    protected $branches;
    
    protected $gitWrapper;
    
    protected $git;
    
    protected $stashStack = 0;
    
    protected $fetched = array();
    
    public function __construct()
    {
        
    }
    
    public function init()
    {
        $this->config = $GLOBALS['c']->get('Config');

        $this->config->setInterface($GLOBALS['c']->get('GitConfiguration'));
        $this->gitConfig = $this->config->getConfig();
        
        $this->initializeRepo();
    }
    
    /**
     * This method will merge two branches and push to your remote fork, defined in your GitConfiguration
     * config file
     *
     * @param array $branchFrom this contains branch, remote alias (optional), merge branch name (optional)
     *              ex: array('b' => 'branch', 'a' => 'origin', 'm' => 'merge_branch_name')
     * @param array $branchTo this contains branch and remote alias (optional), merge branch name (optional)
     *              ex: array('b' => 'branch', 'a' => 'origin', 'm' => 'merge_branch_name')
     * @throws GitException
     */
    public function merge($branchFrom, $branchTo)
    {
        $this->isLoaded();
    
        if (!$this->branchExists($branchFrom)) {
            throw new GitException("Branch " . $this->expandBranchName($branchFrom) . " does not exist in {$this->gitConfig['repository']}");
        }
    
        if (!empty($branchFrom['a']) && !$this->getFetched($branchFrom['a'])) {
            $this->fetch($branchFrom['a']);
        }
    
        if (!$this->branchExists($branchTo)) {
            throw new GitException("Branch " . $this->expandBranchName($branchTo) . " does not exist in {$this->gitConfig['repository']}");
        }
    
        if (!empty($branchTo['a']) && !$this->getFetched($branchTo['a'])) {
            $this->fetch($branchTo['a']);
        }
    
        $beforeMergeBranch = $this->currentBranch();
    
        // If we have changes, we stash them to restore state when we're done
        $this->stash();
    
        $localTempBranchTo = (!empty($branchFrom['m']) ? $branchFrom['m'] : $branchFrom['b']) .
                             "_to_" .
                             (!empty($branchTo['m']) ? $branchTo['m'] : $branchTo['b']) .
                             "_" .
                             gmdate('YmdHi');
        
        $this->git->checkout($this->expandBranchName($branchTo));
        $this->git->checkoutNewBranch($localTempBranchTo);
    
        try {
            $this->git->merge($this->expandBranchName($branchFrom));
        } catch (GitException $e) {
            // TODO: Register actions through a stack processor, that allows you to track how to "undo" everything
            // without having to duplicate calls, see "TODO: look up twice" below
            $this->git->reset(array('hard' => true));
            $this->git->checkout($beforeMergeBranch);
            $this->git->branch($localTempBranchTo, array('D' => true));
            $this->unstash();
    
            throw new GitException("Could not merge " . $this->expandBranchName($branchFrom) .
                " into " . $this->expandBranchName($branchTo) .
                "There may have been conflicts. Please verify.");
        }
        // TODO: log merge success
        $logMe = "Merged " . $this->expandBranchName($branchFrom) .
        " to " . $this->expandBranchName($branchTo) . PHP_EOL;
        var_dump($logMe);
    
        $pushResults = $this->push($this->unexpandBranch("{$this->gitConfig['push_alias']}/{$localTempBranchTo}"));
        if ($pushResults !== TRUE) {
            // TODO: Register actions through a stack processor, that allows you to track how to "undo" everything
            // without having to duplicate calls, see "TODO: look up twice" below
            $this->git->reset(array('hard' => true));
            $this->git->checkout($beforeMergeBranch);
            $this->git->branch($localTempBranchTo, array('D' => true));
            $this->unstash();
    
            throw new GitException("Could not push {$localTempBranchTo} to {$this->gitConfig['push_alias']}. ".
                "Output: " . PHP_EOL . $pushResults);
        }
    
        // TODO: look up twice
        $this->git->reset(array('hard' => true));
        $this->git->checkout($beforeMergeBranch);
        $this->git->branch($localTempBranchTo, array('D' => true));
        $this->unstash();
    
        return "{$this->gitConfig['push_alias']}/{$localTempBranchTo}";
        //var_dump($this->branches);
    }
    
    public function branchExists($branch)
    {
        if (is_array($branch)) {
            return in_array($this->expandBranchName($branch), $this->branches);
        } else {
            return in_array($branch, $this->branches);
        }
    }
    
    public function unexpandBranch($branchString)
    {
        $exploded = explode('/', $branchString);
    
        if (count($exploded) == 1) {
            return $branchString;
        } elseif (count($exploded) == 2) {
            return array('b' => $exploded[1], 'a' => $exploded[0]);
        } elseif (count($exploded) == 3) {
            return array('b' => $exploded[2], 'a' => $exploded[1]);
        } else {
            throw new GitException("Can't unexpand branch strings with more than three sub elements");
        }
    }
    
    public function remoteDelete($branchArray)
    {
        $this->git->push($branchArray['a'], ":{$branchArray['b']}");
    }
    
    protected function push($branchArray)
    {
        $this->git->clearOutput();
        $this->git->push($branchArray['a'], $branchArray['b']);;
        $pushOutput = $this->git->getOutput();
        if (!empty($pushOutput)) {
            return $pushOutput;
        } else {
            // TODO: log this
            $pushed = "{$branchArray['a']}/{$branchArray['b']}";
            if (!$this->branchExists($pushed)) {
                $this->addToBranches(array("remotes/{$pushed}"));
            }
            $logMe = "Successfully pushed {$pushed}!";
            echo $logMe . PHP_EOL;
            
            return TRUE;
        }
    }
    
    protected function initializeRepo()
    {
        $this->gitWrapper = $GLOBALS['c']->get('GitWrapper');
        $this->gitWrapper->setGitBinary($this->gitConfig['gitBinary']);
        
        $this->git = $this->gitWrapper->workingCopy($this->gitConfig['directory']);
        
        if (!$this->git->isCloned()) {
            $this->git->clone($this->gitConfig['repository']);
            // TODO: log
            $logMe = "Cloning repo: {$this->gitConfig['repository']}: Response: " . $this->git->getOutput();
        } else {
            // TODO: log
            $this->git->fetch($this->gitConfig['alias']);
            $logMe = "No need to clone repo {$this->gitConfig['repository']}, ".
                     "as it already exists, fetching instead. Response:" . $this->git->getOutput();
        }
        
        $this->loadBranches(TRUE);
        
        // $this->merge('ibm_r12', 'ibm_r13'); // TODO: delete this line, it's for debugging
        // TODO: throw exception if failure
    }
    
    protected function loadBranches($force = FALSE)
    {
        if (!isset($this->branches) || $force) {
            // TODO: log
            $this->branches = $this->git->getBranches()->fetchBranches();
        }
    }
    
    /**
     * Adds to the globally available branches list, only to be called after push
     * 
     * @param array $branches list of branches, should be already prefixed by 'remotes'!
     */
    protected function addToBranches(Array $branches)
    {
        foreach ($branches as $b) {
            $this->branches[] = $b;
        }
    }
    
    protected function fetch($alias)
    {
        $this->git->fetch($alias);
        $this->setFetched($alias);
    }
    
    protected function getFetched($alias)
    {
        return array_key_exists($alias, $this->fetched) && $this->fetched[$alias];
    }
    
    protected function setFetched($alias, $value = TRUE)
    {
        $this->fetched[$alias] = $value;
    }
    
    protected function currentBranch()
    {
        $this->git->clearOutput();
        $this->git->run(array('rev-parse', '--abbrev-ref', 'HEAD'));
        return trim($this->git->getOutput());
    }
    
    protected function hasStashes()
    {
        return $this->stashStack > 0;
    }
    
    protected function stash()
    {
        if ($this->git->hasChanges()) {
            // TODO: log
            $this->git->run(array('stash'));
            $this->stashStack++;
        }
    }
    
    protected function unstash($all = FALSE)
    {
        // TODO: log
        if ($all) {
            while ($this->hasStashes()) {
                $this->unstashOne();
            }
        } else if ($this->hasStashes()) {
            $this->unstashOne();
        }
    }
    
    protected function unstashOne()
    {
        $this->git->run(array('stash', 'apply'));
        $this->git->run(array('stash', 'drop', 'stash@{0}'));
        $this->stashStack--;
    }
    
    protected function expandBranchName($branchArray)
    {
        if (!empty($branchArray['a'])){
            return "remotes/{$branchArray['a']}/{$branchArray['b']}";
        } else {
            return $branchArray['b'];
        }
    }
    
    protected function isLoaded()
    {
        if (!isset($this->git)) {
            throw new GitException(__CLASS__ . ": method called, but repository not initialized");
        }
    }
}