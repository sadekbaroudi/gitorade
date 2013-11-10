<?php

namespace Sadekbaroudi\Gitorade;

use GitWrapper\GitException;
use Sadekbaroudi\OperationState\OperationStateManager;
use Sadekbaroudi\OperationState\OperationState;
use Sadekbaroudi\Gitorade\Branches\BranchManager;
use GitWrapper\GitWrapper;
use GitWrapper\GitWorkingCopy;
use Github\Client;
use Github\HttpClient\HttpClient;
use Github\Exception\RuntimeException;
use Github\Exception\ValidationFailedException;
use Sadekbaroudi\Gitorade\Branches\BranchRemote;
use Sadekbaroudi\Gitorade\Configuration\Type\GitConfiguration;
use Sadekbaroudi\Gitorade\Configuration\Type\GithubConfiguration;

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
        $this->osm = new OperationStateManager();
        
        $this->configs['gitCli'] = new GitConfiguration();
        $this->configs['github'] = new GithubConfiguration();
        
        $this->github = new Client();
        $this->github->authenticate(
            $this->configs['github']->getConfig('token') ? $this->configs['github']->getConfig('token') : $this->configs['github']->getConfig('username'),
            $this->configs['github']->getConfig('token') ? NULL : $this->configs['github']->getConfig('password'),
            $this->configs['github']->getConfig('token') ? Client::AUTH_HTTP_TOKEN : NULL
        );
        
        $this->gitWrapper = new GitWrapper($this->configs['gitCli']->getConfig('gitBinary'));
        if ($this->configs['gitCli']->getConfig('privateKey')) {
            $this->gitWrapper->setPrivateKey($this->configs['gitCli']->getConfig('privateKey'));
        }
        
        $this->git = $this->gitWrapper->workingCopy($this->configs['gitCli']->getConfig('directory'));
        
        if (!$this->git->isCloned()) {
            $this->git->clone($this->configs['gitCli']->getConfig('repository'));
            // TODO: log
            $logMe = "Cloning repo: " . $this->configs['gitCli']->getConfig('repository') . ": Response: " . $this->git->getOutput();
        } else {
            // TODO: log
            $this->fetch($this->configs['gitCli']->getConfig('alias'));
            $logMe = "No need to clone repo " . $this->configs['gitCli']->getConfig('repository') . ", ".
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
        if (!$this->bm->exists($branchFrom)) {
            throw new GitException("Branch (from) '{$branchFrom}' does not exist in " . $this->configs['gitCli']->getConfig('repository'));
        }
        
        if ($branchFrom->getType() == 'remote' && !$this->getFetched($branchFrom->getAlias())) {
            $this->fetch($branchFrom->getAlias());
        }
    
        if (!$this->bm->exists($branchTo)) {
            throw new GitException("Branch (to) '{$branchTo}' does not exist in " . $this->configs['gitCli']->getConfig('repository'));
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
        
        $pushObject = new BranchRemote("remotes/" . $this->configs['gitCli']->getConfig('push_alias') . "/{$localTempBranchTo}");
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
            if ($branchFrom->getType() != 'remote' || $branchTo->getType() != 'remote') {
                echo "branchTo or branchFrom have empty alias" . PHP_EOL;
                continue;
            }
            $pullRequestArray = array(
                array(
                    'user' => $this->getUserFromRepoString($this->configs['gitCli']->getConfig('repository')),
                    'repo' => $this->getRepoFromRepoString($this->configs['gitCli']->getConfig('repository')),
                    'prContent' => array(
                        'base' => $branchTo->getBranch(),
                        'head' => $pushObject->getAlias() . ':' . $pushObject->getBranch(),
                        'title' => "Merge ".$branchFrom->getMergeName()." to ".$branchTo->getMergeName(),
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
     * This method will simply delete the remote branch specified in $branchObject
     * 
     * @param Sadekbaroudi\Gitorade\Branches\BranchRemote $branchObject object representing branch to delete
     */
    public function remoteDelete($branchObject)
    {
        $this->git->push($branchObject->getAlias(), ":" . $branchObject->getBranch());
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
            throw new GitException("Could not push {$localTempBranchTo} to " . $this->configs['gitCli']->getConfig('push_alias') .
                ". Output: " . PHP_EOL . $pushResults);
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
}