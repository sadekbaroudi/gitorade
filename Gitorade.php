<?php

namespace Sadekbaroudi\Gitorade;

use GitWrapper\GitException;
use Sadekbaroudi\OperationState\OperationStateManager;
use Sadekbaroudi\OperationState\OperationState;
use Sadekbaroudi\Gitorade\Configuration\Config;
use Sadekbaroudi\Gitorade\Branches\BranchManager;
use GitWrapper\GitWrapper;
use GitWrapper\GitWorkingCopy;
use Github\Client;
use Github\HttpClient\HttpClient;
use Github\Exception\RuntimeException;
use Github\Exception\ValidationFailedException;
use Sadekbaroudi\Gitorade\Branches\BranchRemote;

class Gitorade {
    
    /**
     * The config object to pull configs
     * @var Config
     */
    protected $configManager;
    
    /**
     * Contains the necessary configurations for this Class, loaded during initialization
     * @var array
     */
    protected $configs;
    
    /**
     * Placeholder for the GitWrapper object, used to get the GitWorkingCopy object, etc
     * @var GitWrapper
     */
    protected $gitWrapper;
    
    /**
     * Holds the GitWorkingCopy object to make git calls at the command line
     * @var GitWorkingCopy
     */
    protected $git;
    
    /**
     * Github client to make pull requests for the merges
     * @var Client
     */
    protected $github;
    
    /**
     * Contains the branches object, a collection of branch objects
     * @var array
     */
    protected $branches;
    
    /**
     * We track the number of stashes, since the git API doesn't
     * @var integer
     */
    protected $stashStack = 0;
    
    /**
     * We track all the repositories that we have fetched during this script so we don't fetch
     * multiple times.
     * @var array
     */
    protected $fetched = array();
    
    /**
     * We use this object to track all the operations we use, so we can undo in the case of an error
     * @var OperationStateManager
     */
    protected $osm;
    
    public function __construct()
    {
        
    }
    
    /**
     * Initialize the configs and repository, must be called before this class can be used!
     */
    public function init()
    {
        $this->osm = new OperationStateManager();
        
        $this->configManager = $GLOBALS['c']->get('Config');
        
        $this->configManager->setInterface($GLOBALS['c']->get('GitConfiguration'));
        $this->configs['gitCli'] = $this->configManager->getConfig();
        
        $this->configManager->setInterface($GLOBALS['c']->get('GithubConfiguration'));
        $this->configs['github'] = $this->configManager->getConfig();
        
        $this->github = new Client();
        $this->github->authenticate(
            !empty($this->configs['github']['token']) ? $this->configs['github']['token'] : $this->configs['github']['username'],
            !empty($this->configs['github']['token']) ? NULL : $this->configs['github']['password'],
            !empty($this->configs['github']['token']) ? Client::AUTH_HTTP_TOKEN : NULL
        );
        
        $this->gitWrapper = new GitWrapper($this->configs['gitCli']['gitBinary']);
        if (!empty($this->configs['gitCli']['privateKey'])) {
            $this->gitWrapper->setPrivateKey($this->configs['gitCli']['privateKey']);
        }
        
        $this->git = $this->gitWrapper->workingCopy($this->configs['gitCli']['directory']);
        
        if (!$this->git->isCloned()) {
            $this->git->clone($this->configs['gitCli']['repository']);
            // TODO: log
            $logMe = "Cloning repo: {$this->configs['gitCli']['repository']}: Response: " . $this->git->getOutput();
        } else {
            // TODO: log
            $this->fetch($this->configs['gitCli']['alias']);
            $logMe = "No need to clone repo {$this->configs['gitCli']['repository']}, ".
                "as it already exists, fetching instead. Response:" . $this->git->getOutput();
        }
        
        $this->loadBranches(TRUE);
        // TODO: throw exception if failure
    }
        
    /**
     * This method will merge two branches and push to your remote fork, defined in your GitConfiguration
     * config file
     *
     * @param Sadekbaroudi\Gitorade\Branches\Branch $branchFrom Branch object representing the branch merging from
     * @param Sadekbaroudi\Gitorade\Branches\Branch $branchTo Branch object representing the branch merging to
     * @param boolean submit the merge pull request when done merging
     * @throws GitException
     */
    public function merge($branchFrom, $branchTo, $submitPullRequest)
    {
        $this->isLoaded();
        
        if (!$this->bm->exists($branchFrom)) {
            throw new GitException("Branch (from) '{$branchFrom}' does not exist in {$this->configs['gitCli']['repository']}");
        }
        
        if ($branchFrom->getType() == 'remote' && !$this->getFetched($branchFrom->getAlias())) {
            $this->fetch($branchFrom->getAlias());
        }
    
        if (!$this->bm->exists($branchTo)) {
            throw new GitException("Branch (to) '{$branchTo}' does not exist in {$this->configs['gitCli']['repository']}");
        }
    
        if ($branchTo->getType() == 'remote' && !$this->getFetched($branchTo->getAlias())) {
            $this->fetch($branchTo->getAlias());
        }
        
        $beforeMergeBranch = $this->currentBranch();
        
        $this->stash();
        
        $localTempBranchTo = $branchFrom->getMergeName() . "_to_" . $branchTo->getMergeName() . "_" . gmdate('YmdHi');
        
        // TODO: PHP 5.4 supports "new Foo()->method()->method()"
        //       http://docs.php.net/manual/en/migration54.new-features.php
        $os = new OperationState();
        $os->setExecute($this->git, 'checkout', array((string)$branchTo));
        $os->addExecute($this->git, 'checkoutNewBranch', array($localTempBranchTo));
        $os->setUndo($this->git, 'checkout', array($beforeMergeBranch));
        $os->addUndo($this->git, 'branch', array($localTempBranchTo, array('D' => true)));
        $os->addUndo($this->git, 'reset', array(array('hard' => true)));
        
        $this->osm->add($os);
        try {
            echo "checking out {$branchTo}" . PHP_EOL;
            echo "checking out new local branch {$localTempBranchTo}" . PHP_EOL;
            $this->osm->execute($os);
        } catch (OperationStateException $e) {
            $this->osm->undoAll();
            throw $e;
        }
        
        try {
            echo "merging {$branchFrom} to {$branchTo}" . PHP_EOL;
            $this->git->merge((string)$branchFrom);
        } catch (GitException $e) {
            $this->osm->undoAll();
            throw new GitException("Could not merge {$branchFrom} to {$branchTo}. There may have been conflicts. Please verify.");
        }
        // TODO: log merge success
        $logMe = "Merged {$branchFrom} to {$branchTo}" . PHP_EOL;
        
        $pushObject = new BranchRemote("remotes/{$this->configs['gitCli']['push_alias']}/{$localTempBranchTo}");
        $pushObject->setMergeName($branchTo->getBranch());
        
        try {
            echo "Pushing to {$pushObject}" . PHP_EOL;
            $this->push($pushObject);
        } catch (GitException $e) {
            $this->osm->undoAll();
            throw $e;
        }
        
        $this->osm->undoAll();
        
        if ($submitPullRequest) {
            // We don't submit a pull request against a local branch
            if (empty($branchTo['a']) || empty($branchFrom['a'])) {
                echo "branchTo or branchFrom have empty alias" . PHP_EOL;
                continue;
            }
            $pullRequestArray = array(
                array(
                    'user' => $this->getUserFromRepoString($this->configs['gitCli']['repository']),
                    'repo' => $this->getRepoFromRepoString($this->configs['gitCli']['repository']),
                    'prContent' => array(
                        'base' => $branchTo->getBranch(),
                        'head' => $pushObject->getAlias() . ':' . $pushObject->getBranch(),
                        'title' => "Merge ".$branchFrom->getBranch()." to ".$branchTo->getBranch(),
                        'body' => 'Pushed by Gitorade',
                    ),
                )
            );
            echo "Submitting pull request: "; var_dump($pullRequestArray); echo PHP_EOL;
            
            $this->submitPullRequests($pullRequestArray);
        }
        
        return $pushObject;
    }
    
    /**
     * This will submit a pull request through the github API
     * 
     * @param array $pullRequests should be an array of pull requests, in the format:
     *              array(
     *                  array(
     *                      'user' => 'user',
     *                      'repo' => 'repo',
     *                      'prContent' => array(
     *                          'base' => 'baseBranch',
     *                          'head' => 'repo:headBranch',
     *                          'title' => 'title',
     *                          'body' => 'body',
     *                      )
     *                  )
     *              )
     * 
     * @return array results of github calls keyed by the same indexes passed in through $pullRequests
     */
    public function submitPullRequests($pullRequests)
    {
        $this->isLoaded();
        
        $return = array();
        
        foreach ($pullRequests as $k => $pr) {
            try {
                $return[$k] = $this->github->api('pull_request')->create(
                    $pr['user'],
                    $pr['repo'],
                    $pr['prContent']
                );
            } catch (ValidationFailedException $e) {
                // If we have a "no commits between {$branch1} and {$branch2}, we can continue
                if ($e->getCode() == 422) {
                    echo "No commits from {$pr['prContent']['head']} to {$pr['prContent']['base']}" . PHP_EOL;
                    continue;
                } else {
                    echo $e->getCode() . PHP_EOL;
                    throw $e;
                }
            }
        }
        
        return $return;
    }
    
    /**
     * Determine if the branch exists in the branch list for this repo (including all remotes or local branches)
     * 
     * @param string 'localbranchname' or 'remotes/alias/branchname'
     * @return boolean TRUE if this branch exists
     */
    public function branchExists($branch)
    {
        return in_array($branch, $this->git->getBranches()->all());
    }

    
    /**
     * Will take a branch string, and expand it to the array format consumable by this class
     * 
     * @param string $branchString formatted as local branch, alias/branchname, or remotes/alias/branchname
     * @throws GitException
     * @return array returns an array in the format typically consumable by methods in this class
     */
    public function unexpandBranch($branchString)
    {
        $exploded = explode('/', $branchString);
    
        if (count($exploded) == 1) {
            return array('b' => $branchString);
        } elseif (count($exploded) == 2) {
            return array('b' => $exploded[1], 'a' => $exploded[0]);
        } elseif (count($exploded) == 3) {
            return array('b' => $exploded[2], 'a' => $exploded[1]);
        } else {
            throw new GitException("Can't unexpand branch strings with more than three sub elements");
        }
    }
    
    /**
     * This method will simply delete the remote branch specified in $branchObject
     * 
     * @param Sadekbaroudi\Gitorade\Branches\BranchRemote $branchObject object representing branch to delete
     */
    public function remoteDelete($branchObject)
    {
        $this->git->push($branchObject->getAlias(), ":" . $branchObject->getBranch());
    }
    
    /**
     * This will convert any branch string to a local branch name.
     * ex: * remotes/alias/branchname = branchname
     *     * alias/branchname = branchname
     *     * branchname = branchname
     * 
     * @param string $branchString branch string
     * @return string converted to local branch name
     */
    public function localBranchName($branchString)
    {
        $pos = strrpos($branchString, '/');
        if ($pos !== FALSE) {
            $pos++;
        }
    
        return substr($branchString, $pos);
    }
    
    /**
     * This will push the branch to the specified alias/branch, and add to the loaded branches
     * 
     * @param Sadekbaroudi\Gitorade\Branches\BranchRemote $branchObject branch object to push
     * @throws GitException
     */
    protected function push($branchObject)
    {
        $this->git->clearOutput();
        $this->git->push($branchObject->getAlias(), $branchObject->getBranch());
        $pushOutput = $this->git->getOutput();
        if (!empty($pushOutput)) {
            throw new GitException("Could not push {$localTempBranchTo} to {$this->configs['gitCli']['push_alias']}. ".
                "Output: " . PHP_EOL . $pushResults);
        } else {
            // TODO: log this
            $pushed = "remotes/".$branchObject->getAlias()."/".$branchObject->getBranch();
            $this->bm->add($pushed);
            $logMe = "Successfully pushed {$pushed}!";
            echo $logMe . PHP_EOL;
        }
    }
    
    /**
     * Load all the branches to the local class, acts as a cache
     * 
     * @param boolean $force Force the refresh even if we have already loaded the branches
     */
    protected function loadBranches($force = FALSE)
    {
        if (!isset($this->bm) || $force) {
            // TODO: log
            $this->bm = new BranchManager();
            $branchArray = $this->git->getBranches()->all();
            foreach ($branchArray as $branchName) {
                $this->bm->add($branchName);
            }
        }
    }
    
    /**
     * This will fetch the remote alias provided
     * 
     * @param string $alias the remote alias to be fetched
     */
    protected function fetch($alias)
    {
        $this->git->fetch($alias);
        $this->setFetched($alias);
    }
    
    /**
     * Has the remote repo been fetched already?
     * 
     * @param string $alias
     * @return boolean
     */
    protected function getFetched($alias)
    {
        return array_key_exists($alias, $this->fetched) && $this->fetched[$alias];
    }
    
    /**
     * Set the remote alias fetched value
     * 
     * @param string $alias
     * @param boolean $value
     */
    protected function setFetched($alias, $value = TRUE)
    {
        $this->fetched[$alias] = $value;
    }
    
    /**
     * Get the branch that the git repository has currently checked out
     * 
     * @return string
     */
    protected function currentBranch()
    {
        $this->git->clearOutput();
        $this->git->run(array('rev-parse', '--abbrev-ref', 'HEAD'));
        return trim($this->git->getOutput());
    }
    
    /**
     * Return true if we have stashes that we have already executed
     * 
     * @return boolean
     */
    protected function hasStashes()
    {
        return $this->stashStack > 0;
    }
    
    /**
     * Stash the current changes, only if there have been uncommitted changes
     */
    protected function stash()
    {
        if ($this->git->hasChanges()) {
            // If we have changes, we stash them to restore state when we're done
            $os = new OperationState();
            $os->setExecute($this->git, 'run', array(array('stash')));
            $os->setUndo($this, 'unstash');
            $this->osm->add($os);
            
            // TODO: log
            $this->osm->execute($os);
            $this->stashStack++;
        }
    }
    
    /**
     * Unstash changes, one if the $all param is FALSE, unstash all if it's true
     * 
     * @param boolean $all determines whether we unstash all stashes we have applied, or just one
     */
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
    
    /**
     * DO NOT USE THIS FUNCTION, USE $this->unstash()
     */
    protected function unstashOne()
    {
        $this->git->run(array('stash', 'apply'));
        $this->git->run(array('stash', 'drop', 'stash@{0}'));
        $this->stashStack--;
    }
    
    protected function getUserFromRepoString($repoString)
    {
        $userAndRepo = substr($repoString, strrpos($repoString, ":") + 1);
        return substr($userAndRepo, 0, strrpos($userAndRepo, "/"));
    }
    
    protected function getRepoFromRepoString($repoString)
    {
        $userAndRepo = substr($repoString, strrpos($repoString, ":") + 1);
        return str_replace(".git", "", substr($userAndRepo, strrpos($userAndRepo, "/") + 1));
    }
    
    /**
     * Assert that the init() function has already been called
     * 
     * @throws GitException
     */
    protected function isLoaded()
    {
        if (!isset($this->git)) {
            throw new GitException(__CLASS__ . ": method called, but repository not initialized");
        }
    }
}