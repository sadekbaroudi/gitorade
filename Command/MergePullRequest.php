<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Sadekbaroudi\Gitorade\Command;
use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Gitorade;

class MergePullRequest extends GitoradeCommand
{
    protected $requiredOptions = array(
        'pull',
    );
    
    protected $prFormatString = '$user/$repo/$number';
    
    public function __construct($name = NULL)
    {
        parent::__construct($name);
    }
    
    protected function configure()
    {
        $this
            ->setName('merge-pr')
            ->setDescription('Merge a pull request from the command line by number')
            ->addOption(
               'debug',
               'd',
               InputOption::VALUE_NONE,
               'turn on debug mode'
            )
            ->addOption(
                'pull',
                NULL,
                InputOption::VALUE_REQUIRED,
                "The pull request to merge (formatted '{$this->prFormatString}')"
            )
            ->addOption(
                'message',
                NULL,
                InputOption::VALUE_REQUIRED,
                "The merge commit message submitted on the pull request"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        
        $this->validateOptions();
        
        $this->initializeOptions();
        
        $result = $this->git->getGithubClient()->api('pull_request')->merge(
            $this->calculated['pull']['user'],
            $this->calculated['pull']['repo'],
            $this->calculated['pull']['number'],
            $this->options['message']
        );
        
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
        if (is_null($this->options['message'])) {
            $this->options['message'] = '';
        }
        
        $branchArgArray = $this->splitPullRequestArg('pull');
        
        $this->calculated['pull']['user'] = $branchArgArray[0];
        $this->calculated['pull']['repo'] = $branchArgArray[1];
        $this->calculated['pull']['number'] = $branchArgArray[2];
    }
    
    protected function splitPullRequestArg($arg)
    {
        $argArray = explode('/', $this->options[$arg]);
    
        if (count($argArray) != 3) {
            throw new \ErrorException("Argument '$arg' ({$this->options[$arg]}) is not in the proper format ({$this->prFormatString})");
        }
    
        return $argArray;
    }
    
    protected function formatPullRequestResponse($prResponse)
    {
        if (!empty($prResponse['sha'])) {
            return "Merged with commit hash {$prResponse['sha']}";
        } else {
            return "Not merged, please check github UI.";
        }
        
    }
}
