<?php

namespace Sadekbaroudi\Gitorade;

use GitWrapper\GitException;
use Sadekbaroudi\OperationState\OperationStateManager;
use Sadekbaroudi\OperationState\OperationState;
use Sadekbaroudi\Gitorade\Branches\BranchManager;
use Sadekbaroudi\Gitorade\Branches\BranchRemote;
use Sadekbaroudi\Gitorade\Configuration\Type\GitConfiguration;
use Sadekbaroudi\Gitorade\Configuration\Type\GithubConfiguration;
use GitWrapper\GitWrapper;
use GitWrapper\GitWorkingCopy;
use Github\Client;
use Github\HttpClient\HttpClient;
use Github\Exception\RuntimeException;
use Github\Exception\ValidationFailedException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Sadekbaroudi\Gitorade\Branches\BranchPullRequest;
use Sadekbaroudi\Gitorade\Branches\BranchMerge;
use Sadekbaroudi\Gitorade\Branches\BranchLocal;
use Sadekbaroudi\Gitorade\Branches\BranchGithub;

class Gitorade {
    
    const NO_COMMITS = 422;
    
    /**
     * The ContainerBuilder object that defines the necessary classes for this object
     * @var ContainerBuilder
     */
    protected $container;
    
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
    protected $githubClient;
    
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
    
    /**
     * We use the branch manager to track the branches we have available in Gitorade
     * @var BranchManager
     */
    protected $bm;
    
    /**
     * Create a Gitorade object, used to run various git operations on the a git checkout, and a github account
     * 
     * @param ContainerBuilder $container must contain references for:
     *                         OperationStateManager -> Sadekbaroudi\OperationState\OperationStateManager
     *                         GitConfiguration -> Sadekbaroudi\Gitorade\Configuration\Type\GitConfiguration
     *                         GithubConfiguration -> Sadekbaroudi\Gitorade\Configuration\Type\GithubConfiguration
     *                         GithubClient -> Github\Client
     *                         GitWrapper -> GitWrapper\GitWrapper
     *                         BranchManager -> Sadekbaroudi\Gitorade\Branches\BranchManager
     */
    public function __construct($container)
    {
        $this->setContainer($container);
    }
    
    public function setContainer($container)
    {
        $this->container = $container;
    }
    
    public function getContainer()
    {
        return $this->container;
    }
    
    public function setOsm(OperationStateManager $osm)
    {
        $this->osm = $osm;
    }
    
    public function getOsm()
    {
        return $this->osm;
    }
    
    public function setGitCliConfig(GitConfiguration $gitCli)
    {
        $this->configs['gitCli'] = $gitCli;
    }
    
    public function getGitCliConfig()
    {
        if (!isset($this->configs['gitCli'])) {
            throw new RuntimeException("gitCli parameter not set in configs property");
        }
        return $this->configs['gitCli'];
    }
    
    public function setGithubConfig(GithubConfiguration $github)
    {
        $this->configs['github'] = $github;
    }
    
    public function getGithubConfig()
    {
        if (!isset($this->configs['github'])) {
            throw new RuntimeException("github parameter not set in configs property");
        }
        return $this->configs['github'];
    }
    
    public function setGithubClient(Client $githubClient)
    {
        $this->githubClient = $githubClient;
    }
    
    public function getGithubClient()
    {
        return $this->githubClient;
    }
    
    public function setGitWrapper(GitWrapper $gitWrapper)
    {
        $this->gitWrapper = $gitWrapper;
    }
    
    public function getGitWrapper()
    {
        return $this->gitWrapper;
    }
    
    public function setGit(GitWorkingCopy $git)
    {
        $this->git = $git;
    }
    
    public function getGit()
    {
        return $this->git;
    }
    
    public function setBm(BranchManager $bm)
    {
        $this->bm = $bm;
    }
    
    public function getBm()
    {
        return $this->bm;
    }
    
    protected function setStashStack($int)
    {
        $this->stashStack = $int;
    }
    
    protected function getStashStack()
    {
        return $this->stashStack;
    }
    
    public function initialize()
    {
        $this->setOsm($this->getContainer()->get('OperationStateManager'));
        
        $this->setGitCliConfig($this->getContainer()->get('GitConfiguration'));
        $this->setGithubConfig($this->getContainer()->get('GithubConfiguration'));
        
        $this->setGithubClient($this->getContainer()->get('GithubClient'));
        
        $this->getGithubClient()->authenticate(
            $this->getGithubConfig()->getConfig('token') ? $this->getGithubConfig()->getConfig('token') : $this->getGithubConfig()->getConfig('username'),
            $this->getGithubConfig()->getConfig('token') ? NULL : $this->getGithubConfig()->getConfig('password'),
            $this->getGithubConfig()->getConfig('token') ? Client::AUTH_HTTP_TOKEN : NULL
        );
        
        $this->getContainer()->setParameter('GitWrapper.git_binary', $this->getGitCliConfig()->getConfig('gitBinary'));
        $this->setGitWrapper($this->getContainer()->get('GitWrapper'));
        
        if ($this->getGitCliConfig()->getConfig('privateKey')) {
            $this->getGitWrapper()->setPrivateKey($this->getGitCliConfig()->getConfig('privateKey'));
        }
        
        $this->setGit($this->getGitWrapper()->workingCopy($this->getGitCliConfig()->getConfig('directory')));
        
        if (!$this->getGit()->isCloned()) {
            $this->getGit()->cloneRepository($this->getGitCliConfig()->getConfig('repository'));
        } else {
            $this->fetch($this->getGitCliConfig()->getConfig('alias'));
        }
        
        $this->setBm($this->getContainer()->get('BranchManager'));
        $branchArray = $this->getGit()->getBranches()->all();
        foreach ($branchArray as $branchName) {
            $this->getBm()->add($branchName);
        }
    }
    
    /**
     * This method will merge two branches and push to your remote fork, defined in your GitConfiguration
     * config file
     *
     * @param Sadekbaroudi\Gitorade\Branches\BranchMerge $branchMerge BranchMerge object representing the from and to branches
     * @param boolean submit the merge pull request when done merging
     * @throws GitException
     */
    public function merge(BranchMerge $branchMerge)
    {
        $this->validateForMerge($branchMerge);
        
        $beforeMergeBranch = $this->currentBranch();
        
        $this->stash();
        
        // TODO: PHP 5.4 supports "new Foo()->method()->method()"
        //       http://docs.php.net/manual/en/migration54.new-features.php
        $os = new OperationState();
        $os->setExecute(array($this->getGit(), 'checkout'), array((string)$branchMerge->getBranchTo()));
        $os->addExecute(array($this->getGit(), 'checkoutNewBranch'), array($branchMerge->getMergeName()));
        $os->setUndo(array($this->getGit(), 'reset'), array(array('hard' => true)));
        $os->addUndo(array($this->getGit(), 'checkout'), array($beforeMergeBranch));
        $os->addUndo(array($this->getGit(), 'branch'), array($branchMerge->getMergeName(), array('D' => true)));
        
        $this->getOsm()->add($os);
        try {
            echo "checking out " . $branchMerge->getBranchTo() . PHP_EOL;
            echo "checking out new local branch " . $branchMerge->getMergeName() . PHP_EOL;
            $this->getOsm()->execute($os);
        } catch (OperationStateException $e) {
            $this->getOsm()->undoAll();
            throw $e;
        }
        
        try {
            echo "merging " . $branchMerge->getBranchFrom() . " to " . $branchMerge->getBranchTo() . PHP_EOL;
            $this->getGit()->merge((string)$branchMerge->getBranchFrom());
        } catch (GitException $e) {
            $this->getOsm()->undoAll();
            throw new GitException("Could not merge " . $branchMerge->getBranchFrom() . " to " . $branchMerge->getBranchTo() . ". There may have been conflicts. Please verify.");
        }
        // TODO: log merge success
        $logMe = "Merged " . $branchMerge->getBranchFrom() . " to " . $branchMerge->getBranchTo() . PHP_EOL;
        
        // Begin with pushing merged branch to remote
        $remoteBranch = new BranchRemote("remotes/" . $this->getGitCliConfig()->getConfig('push_alias') . "/" . $branchMerge->getMergeName());
        $remoteBranch->setMergeName($branchMerge->getBranchTo()->getBranch());
        
        $localBranch = new BranchLocal($branchMerge->getMergeName());
        $localBranch->setRemote($remoteBranch);
        
        try {
            echo "Pushing to {$localBranch}" . PHP_EOL;
            $this->push($localBranch);
        } catch (GitException $e) {
            $this->getOsm()->undoAll();
            throw $e;
        }
        
        $this->getOsm()->undoAll();
        
        // Delete this line after testing
        //return $remoteBranch;
        
        $returnArray = array(
            'remoteBranch' => $remoteBranch,
        );
        
        
        // Begin with pull request preparation
        // Set pull request data on remoteBranch
        if ($branchMerge->getBranchTo()->getType() == 'remote') {
        
            $returnArray['pullRequest'] = new BranchPullRequest(
                new BranchGithub(
                    $this->getGitCliConfig()->getConfig('push_alias'), // it doesn't matter what user, since it uses the logged in user 
                    $branchMerge->getBranchFrom()->getAlias(),
                    $branchMerge->getBranchFrom()->getBranch()
                ),            
                new BranchGithub(
                    $this->getUserFromRepoString($this->getGitCliConfig()->getConfig('repository')),
                    $this->getRepoFromRepoString($this->getGitCliConfig()->getConfig('repository')),
                    $branchMerge->getBranchTo()->getBranch()
                ),
                "Merge ".$branchMerge->getBranchFrom()->getBranch() . " to " . $branchMerge->getBranchTo()->getBranch(),
                "Pushed by Gitorade"
            );
            
        } else {
            // We don't submit a pull request against a local branch
            $logMe = "Can't prepare pull request since branchFrom and/or branchTo have an empty alias";
            echo $logMe . PHP_EOL;
            //throw new GitException("branchTo or branchFrom have empty alias");
        }
        
        return $returnArray;
    }
    
    /**
     * This method will validate the from and to branches for merging, as well as fetch the associated branch data from the remote alias
     *
     * @param Sadekbaroudi\Gitorade\Branches\BranchMerge $branchMerge BranchMerge object representing the from and to branches
     * @throws GitException
     */
    protected function validateForMerge(BranchMerge $branchMerge)
    {
        if (!$this->getBm()->exists($branchMerge->getBranchFrom())) {
            throw new GitException("Branch (from) '" . $branchMerge->getBranchFrom() . "' does not exist in " . $this->getGitCliConfig()->getConfig('repository'));
        }
        
        if ($branchMerge->getBranchFrom()->getType() == 'remote' && !$this->getFetched($branchMerge->getBranchFrom()->getAlias())) {
            $this->fetch($branchMerge->getBranchFrom()->getAlias());
        }
        
        if (!$this->getBm()->exists($branchMerge->getBranchTo())) {
            throw new GitException("Branch (to) '" . $branchMerge->getBranchTo() . "' does not exist in " . $this->getGitCliConfig()->getConfig('repository'));
        }
        
        if ($branchMerge->getBranchTo()->getType() == 'remote' && !$this->getFetched($branchMerge->getBranchTo()->getAlias())) {
            $this->fetch($branchMerge->getBranchTo()->getAlias());
        }
    }
    
    /**
     * Singular version of call to submitPullRequests, submits a pull request through the github API
     * 
     * @param Sadekbaroudi\Gitorade\Branches\BranchPullRequest $branchPr a BranchPullRequest object with branchFrom and branchTo
     * @return array results of pull request create execution
     */
    public function submitPullRequest(BranchPullRequest $branchPr)
    {
        if (!$branchPr->canSubmitPullRequest()) {
            throw new GitException(__METHOD__ . ": canSubmitPullRequest returned false, bailing: {$branchPr}");
        }
        
        $pr = array(
            'user' => $branchPr->getBranchTo()->getUser(),
            'repo' => $branchPr->getBranchTo()->getRepo(),
            'prContent' => array(
                'base' => $branchPr->getBranchTo()->getBranch(),
                'head' => $branchPr->getBranchFrom()->getRepo() . ':' . $branchPr->getBranchFrom()->getBranch(),
                'title' => $branchPr->getTitle(),
                'body' => $branchPr->getBody(),
            )
        );
        
        try {
            $return = $this->getGithubClient()->api('pull_request')->create(
                $pr['user'],
                $pr['repo'],
                $pr['prContent']
            );
        
            echo "Submitting pull request: "; var_dump($pr); echo PHP_EOL;
        
        } catch (ValidationFailedException $e) {
            // If we have a "no commits between {$branch1} and {$branch2}, we can continue
            if ($e->getCode() == self::NO_COMMITS) {
                $logMe = "No commits from {$pr['prContent']['head']} to {$pr['prContent']['base']}";
                echo $logMe . PHP_EOL . PHP_EOL;
                return FALSE;
            } else {
                echo $e->getCode() . PHP_EOL;
                throw $e;
            }
        }
        
        return $return;
    }
    
    /**
     * This will submit an array of pull requests via the Singualar submitPullRequest method
     *
     * @param array $prObjects should be an array of BranchPullRequest objects with
     *                           branchFrom and branchTo populated.
     *
     * @return array results of github calls keyed by the same indexes passed in through $prObjects
     */
    public function submitPullRequests($prObjects)
    {
        $return = array();
        
        foreach ($prObjects as $k => $po) {
            $return[$k] = $this->submitPullRequest($po);
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
        $this->getGit()->push($branchObject->getAlias(), ":" . $branchObject->getBranch());
    }
    
    /**
     * This will push the branch to the specified alias/branch, and add to the loaded branches
     * 
     * @param Sadekbaroudi\Gitorade\Branches\BranchLocal $branchLocal branch object to push
     * @throws GitException
     */
    protected function push(BranchLocal $branchLocal)
    {
        if (!$branchLocal->hasRemote()) {
            throw new GitException(__METHOD__ . ": The BranchLocal object must have a remote branch!");
        }
        
        $this->getGit()->clearOutput();
        $this->getGit()->push($branchLocal->getRemote()->getAlias(), $branchLocal->getRemote()->getBranch());
        $pushOutput = $this->getGit()->getOutput();
        if (!empty($pushOutput)) {
            throw new GitException("Could not push to " . $branchLocal->getRemote()->getAlias() .
                ". Output: " . PHP_EOL . $pushResults);
        } else {
            // TODO: log this
            $pushed = "remotes/".$branchLocal->getRemote()->getAlias()."/".$branchLocal->getRemote()->getBranch();
            $this->getBm()->add($pushed);
            $logMe = "Successfully pushed {$pushed}!";
            echo $logMe . PHP_EOL;
        }
    }
    
    /**
     * This will fetch the remote alias provided
     * 
     * @param string $alias the remote alias to be fetched
     */
    protected function fetch($alias)
    {
        $this->getGit()->fetch($alias);
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
        $this->getGit()->clearOutput();
        $this->getGit()->run(array('rev-parse', '--abbrev-ref', 'HEAD'));
        return trim($this->getGit()->getOutput());
    }
    
    /**
     * Return true if we have stashes that we have already executed
     * 
     * @return boolean
     */
    protected function hasStashes()
    {
        return $this->getStashStack() > 0;
    }
    
    /**
     * Stash the current changes, only if there have been uncommitted changes
     */
    protected function stash()
    {
        if ($this->getGit()->hasChanges()) {
            // If we have changes, we stash them to restore state when we're done
            $os = new OperationState();
            $os->setExecute(array($this->getGit(), 'run'), array('stash'));
            $os->setUndo(array($this, 'unstash'), OperationState::NO_ARGUMENT);
            $this->getOsm()->add($os);
            
            // TODO: log
            $this->getOsm()->execute($os);
            $this->setStashStack($this->getStashStack() + 1);
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
        $this->getGit()->run(array('stash', 'apply'));
        $this->getGit()->run(array('stash', 'drop', 'stash@{0}'));
        $this->setStashStack($this->getStashStack() - 1);
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