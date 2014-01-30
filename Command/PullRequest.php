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

class PullRequest extends GitoradeCommand
{
    protected $requiredOptions = array(
    	'base_repo',
        'base_branch',
        'head_repo',
        'head_branch',
    );
    
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
                'base_repo',
                NULL,
                InputOption::VALUE_REQUIRED,
                'To: the base repository where the branch exists'
            )
            ->addOption(
	            'base_branch',
                NULL,
                InputOption::VALUE_REQUIRED,
                'To: The base branch where the pull request commits will go'
            )
            ->addOption(
                'head_repo',
                NULL,
                InputOption::VALUE_REQUIRED,
                'From: the head repository where the branch exists'
            )
            ->addOption(
                'head_branch',
                NULL,
                InputOption::VALUE_REQUIRED,
                'From: the head branch where the pull request commits come from'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateOptions();
        
        parent::execute($input, $output);
        
        var_dump($this->options);
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
            throw new \ErrorException("The following options were not filled in:" . PHP_EOL . '* ' . implode(PHP_EOL . '* ', $failed));
        }
    }
}
