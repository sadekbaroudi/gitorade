<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Sadekbaroudi\Gitorade\Command;
use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Gitorade;
use Tree\Node\Node;
use Sadekbaroudi\Gitorade\Branches\BranchPullRequest;
use Sadekbaroudi\Gitorade\Branches\BranchGithub;

class PullRequest extends GitoradeCommand
{
    /**
     * @var Array calculated options based on the user passed arguments (options)
     */
    protected $calculated = array();
    
    protected $requiredOptions = array(
        'base',
        'head',
        'title',
    );
    
    protected $branchFormatString = '$user/$repo/$branch';
    
    public function __construct($name = NULL)
    {
        parent::__construct($name);
    }
    
    protected function configure()
    {
        $this
            ->setName('pull-request')
            ->setDescription('Submit a pull request from the command line')
            ->addOption(
               'debug',
               'd',
               InputOption::VALUE_NONE,
               'turn on debug mode'
            )
            ->addOption(
                'base',
                NULL,
                InputOption::VALUE_REQUIRED,
                "To branch: the base user, repo, and branch (formatted '{$this->branchFormatString}')"
            )
            ->addOption(
                'head',
                NULL,
                InputOption::VALUE_REQUIRED,
                "From branch: the head user, repo, and branch (formatted '{$this->branchFormatString}')"
            )
            ->addOption(
                'title',
                NULL,
                InputOption::VALUE_REQUIRED,
                'The title of the pull request'
            )
            ->addOption(
                'body',
                '',
                InputOption::VALUE_OPTIONAL,
                'The body (description) of the pull request'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        
        $this->validateOptions();
        
        $this->initializeOptions();
        
        $branchPr = new BranchPullRequest(
            new BranchGithub(
                $this->calculated['head']['user'], // it doesn't matter what user, since it uses the logged in user
                $this->calculated['head']['repo'],
                $this->calculated['head']['branch']
            ),
            new BranchGithub(
                $this->calculated['base']['user'],
                $this->calculated['base']['repo'],
                $this->calculated['base']['branch']
            ),
            $this->options['title'],
            $this->options['body']
        );
        
        $result = $this->git->submitPullRequest($branchPr);
        
        $output->write($this->formatPullRequestResponse($result) . PHP_EOL);
    }
    
    protected function validateOptions()
    {
        $failed = array();
        
        foreach ($this->requiredOptions as $option) {
            if (is_null($this->options[$option])) {
                $failed[] = $option;
            }
        }
        
        if (!empty($failed)) {
            throw new \ErrorException("The following required options were not filled in:" . PHP_EOL . '* ' . implode(PHP_EOL . '* ', $failed));
        }
    }
    
    protected function initializeOptions()
    {
        if (is_null($this->options['body'])) {
            $this->options['body'] = '';
        }
        
        foreach (array('head', 'base') as $branchArg) {
            $branchArgArray = $this->splitBranchArg($branchArg);
            
            $this->calculated[$branchArg]['user'] = $branchArgArray[0];
            $this->calculated[$branchArg]['repo'] = $branchArgArray[1];
            $this->calculated[$branchArg]['branch'] = $branchArgArray[2];
        }
    }
    
    protected function splitBranchArg($arg)
    {
        $argArray = explode('/', $this->options[$arg]);
    
        if (count($argArray) != 3) {
            throw new \ErrorException("Argument '$arg' ({$this->options[$arg]}) is not in the proper format ({$this->branchFormatString})");
        }
    
        return $argArray;
    }
    
    protected function formatPullRequestResponse($prResponse)
    {
        if (!empty($prResponse['number'])) {
            return "Pull request number {$prResponse['number']} submitted!";
        } else {
            return "No pull request submitted.";
        }
    }
}
