<?php

namespace Sadekbaroudi\Gitorade;

use GitWrapper\GitException;
use Sadekbaroudi\OperationState\OperationStateManager;
use Sadekbaroudi\OperationState\OperationState;
use Sadekbaroudi\Gitorade\Configuration\Config;
use GitWrapper\GitWrapper;
use GitWrapper\GitWorkingCopy;
use Github\Client;
use Github\HttpClient\HttpClient;
use Github\Exception\RuntimeException;
use Github\Exception\ValidationFailedException;

class Gitorade {
    
    /**
     * The config object to pull configs
     * @var Config
     */
    protected $config;
    
    /**
     * Contains the git configuration for pulling data
     * @var array
     */
    protected $gitConfig;
    
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
     * Contains the github configuration for creating pull requests
     * @var array
     */
    protected $githubConfig;
    
    /**
     * Contains the branches list for reference
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
        $this->config = $GLOBALS['c']->get('Config');

        $this->config->setInterface($GLOBALS['c']->get('GitConfiguration'));
        $this->gitConfig = $this->config->getConfig();
        
        $this->osm = new OperationStateManager();
        
        $this->config->setInterface($GLOBALS['c']->get('GithubConfiguration'));
        $this->githubConfig = $this->config->getConfig();
        
        $this->github = new Client();
        $this->github->authenticate(
            !empty($this->githubConfig['token']) ? $this->githubConfig['token'] : $this->githubConfig['username'],
            !empty($this->githubConfig['token']) ? NULL : $this->githubConfig['password'],
            !empty($this->githubConfig['token']) ? Client::AUTH_HTTP_TOKEN : NULL
        );
        
        $this->initializeRepo();
    }
    
    /**
     *  This should be called by the init() function only. Initializes the repo by cloning if necessary,
     *  and fetching if it's already cloned. It also sets up the class git objects.
     */
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
        // TODO: throw exception if failure
    }
    
    /**
     * This method will merge two branches and push to your remote fork, defined in your GitConfiguration
     * config file
     *
     * @param array $branchFrom this contains branch, remote alias (optional), merge branch name (optional)
     *              ex: array('b' => 'branch', 'a' => 'origin', 'm' => 'merge_branch_name')
     * @param array $branchTo this contains branch and remote alias (optional), merge branch name (optional)
     *              ex: array('b' => 'branch', 'a' => 'origin', 'm' => 'merge_branch_name')
     * @param boolean submit the merge pull request when done merging
     * @throws GitException
     */
    public function merge($branchFrom, $branchTo, $submitPullRequest)
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
    
        $this->stash();
        
        $localTempBranchTo = (!empty($branchFrom['m']) ? $branchFrom['m'] : $branchFrom['b']) .
                             "_to_" .
                             (!empty($branchTo['m']) ? $branchTo['m'] : $branchTo['b']) .
                             "_" .
                             gmdate('YmdHi');
        
        // TODO: PHP 5.4 supports "new Foo()->method()->method()"
        //       http://docs.php.net/manual/en/migration54.new-features.php
        $os = new OperationState();
        $os->setExecute($this->git, 'checkout', array($this->expandBranchName($branchTo)));
        $os->addExecute($this->git, 'checkoutNewBranch', array($localTempBranchTo));
        $os->setUndo($this->git, 'branch', array($localTempBranchTo, array('D' => true)));
        $os->addUndo($this->git, 'checkout', array($beforeMergeBranch));
        $os->addUndo($this->git, 'reset', array(array('hard' => true)));
        
        $this->osm->add($os);
        try {
            $this->osm->execute($os);
        } catch (OperationStateException $e) {
            $this->osm->undoAll();
            throw $e;
        }
        
        try {
            $this->git->merge($this->expandBranchName($branchFrom));
        } catch (GitException $e) {
            $this->osm->undoAll();
    
            throw new GitException("Could not merge " . $this->expandBranchName($branchFrom) .
                " into " . $this->expandBranchName($branchTo) .
                "There may have been conflicts. Please verify.");
        }
        // TODO: log merge success
        $logMe = "Merged " . $this->expandBranchName($branchFrom) .
        " to " . $this->expandBranchName($branchTo) . PHP_EOL;
        var_dump($logMe);
        
        $pushName = "{$this->gitConfig['push_alias']}/{$localTempBranchTo}";
        
        try {
            $this->push($this->unexpandBranch($pushName));
        } catch (GitException $e) {
            $this->osm->undoAll();
            throw $e;
        }
        
        $this->osm->undoAll();
        
        echo "submitPullRequest: "; var_dump($submitPullRequest);
        if ($submitPullRequest) {
            // We don't submit a pull request against a local branch
            if (empty($branchTo['a']) || empty($branchFrom['a'])) {
                echo "branchTo or branchFrom have empty alias";
                var_dump($branchTo);
                var_dump($branchFrom);
                continue;
            }
            $pullRequestArray = array(
                array(
                    'user' => $this->getUserFromRepoString($this->gitConfig['repository']),
                    'repo' => $this->getRepoFromRepoString($this->gitConfig['repository']),
                    'prContent' => array(
                        'base' => "{$branchTo['b']}",
                        'head' => str_replace("/", ":", $pushName),
                        'title' => "Merge {$branchFrom['b']} to {$branchTo['b']}",
                        'body' => 'Pushed by Gitorade',
                    ),
                )
            );
            
            $this->submitPullRequests($pullRequestArray);
        }
        
        return $pushName;
        //var_dump($this->branches);
    }
    
    /**
     * This will submit a pull request through the github API
     * 
     * @param array $pullRequests should be an array of pull requests in the format:
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
                    continue;
                } else {
                    echo $e->getCode() . "\n";
                    throw $e;
                }
            }
        }
        
        return $return;
    }
    
    /**
     * Determine if the branch exists in the branch list for this repo (including all remotes or local branches)
     * 
     * @param string|array $branch can be a branch array or expanded string
     *              ex 1: $branch = 'remotes/repo_alias/branch_name' // remote branch
     *              ex 2: $branch = 'branch_name' // local branch
     *              ex 3: $branch = array('a' => 'repo_alias', 'b' => 'branch_name') // remote branch
     *              ex 4: $branch = array('b' => 'branch_name') // local branch
     * @return boolean TRUE if this branch exists
     */
    public function branchExists($branch)
    {
        if (is_array($branch)) {
            return in_array($this->expandBranchName($branch), $this->branches);
        } else {
            return in_array($branch, $this->branches);
        }
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
     * This method will simply delete the remote branch specified in $branchArray
     * 
     * @param array $branchArray array formatted with $branchArray['a'] being the
     *                           repo alias and $branchArray['b'] being the branch
     */
    public function remoteDelete($branchArray)
    {
        $this->git->push($branchArray['a'], ":{$branchArray['b']}");
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
     * @param array $branchArray branch in the standard array format ($branchArray = array('a' => 'alias', 'b' => 'branch')
     * @throws GitException
     */
    protected function push($branchArray)
    {
        $this->git->clearOutput();
        $this->git->push($branchArray['a'], $branchArray['b']);;
        $pushOutput = $this->git->getOutput();
        if (!empty($pushOutput)) {
            throw new GitException("Could not push {$localTempBranchTo} to {$this->gitConfig['push_alias']}. ".
                "Output: " . PHP_EOL . $pushResults);
        } else {
            // TODO: log this
            $pushed = "{$branchArray['a']}/{$branchArray['b']}";
            if (!$this->branchExists($pushed)) {
                $this->addToBranches(array($this->prefixRemotes($pushed)));
            }
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
    
    /**
     * Will return a string of the full branch name, provided a branch array
     * 
     * @param array $branchArray
     * @return string expanded full branch name based on a provided array
     */
    protected function expandBranchName($branchArray)
    {
        if (!empty($branchArray['a'])){
            return $this->prefixRemotes("{$branchArray['a']}/{$branchArray['b']}");
        } else {
            return $branchArray['b'];
        }
    }
    
    /**
     * Prefixes the "remotes" part of the branch string, unless it's already there
     * 
     * @param string $branchString
     * @return string
     */
    protected function prefixRemotes($branchString)
    {
        if (substr($branchString, 0, 8) == 'remotes/') {
            return $branchString;
        } else {
            return "remotes/{$branchString}";
        }
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