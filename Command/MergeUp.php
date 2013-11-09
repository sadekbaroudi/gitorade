<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Sadekbaroudi\Gitorade\Command;
use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Branches\BranchManager;

class MergeUp extends GitoradeCommand
{
    protected $branchConfig;
    
    protected $options;
    
    protected $bm;
    
    public function __construct($name = NULL)
    {
        $this->setBranchManager(new BranchManager());
        parent::__construct($name);
    }
    
    public function setBranchManager($bm)
    {
        $this->bm = $bm;
    }
    
    protected function configure()
    {
        $this
            ->setName('merge-up')
            ->setDescription('Merge branches up based on branch structure definition')
            ->addOption(
               'debug',
               'd',
               InputOption::VALUE_NONE,
               'turn on debug mode'
            )
            ->addOption(
                'pull-request',
                'p',
                InputOption::VALUE_NONE,
                'push the created merge branches to your fork and create pull requests'
            )
            ->addOption(
                'interactive',
                'i',
                InputOption::VALUE_NONE,
                'enables interactive mode, where you will be prompted before certain actions are taken'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->options = $input->getOptions();
        
        $this->git = $GLOBALS['c']->get('Gitorade');
        $this->git->init();
        
        $this->config = $GLOBALS['c']->get('Config');
        $this->config->setInterface($GLOBALS['c']->get('BranchConfiguration'));
        
        $pushedBranches = $this->mergeUp($this->config->getConfig());
    }
    
    protected function mergeUp(Array $branchConfig, $unmerged = NULL)
    {
        $pushedObjects = array();
        
        foreach ($branchConfig as $key => $val) {
            if (is_array($val)) {
                if (empty($unmerged)) {
                    $unmerged = $this->bm->getBranchObjectByName($key);
                    $unmerged->setMergeName($this->git->localBranchName($key));
                    
                    // Recursive call, since we have nothing to merge yet
                    $this->mergeUp($val, $unmerged);
                } else {
                    $to = $this->bm->getBranchObjectByName($key);
                    $to->setMergeName($this->git->localBranchName($key));
                    
                    // Call the merge!
                    $result = $this->git->merge($unmerged, $to, $this->options['pull-request']);
                    
                    // Store the pushed branches
                    $pushedObjects[] = $result;
                    
                    // Recursive call with next step, including shorthand
                    $this->mergeUp($val, $result);
                }
            } else {
                if (empty($unmerged)) {
                    $from = $this->bm->getBranchObjectByName($key);
                    $from->setMergeName($this->git->localBranchName($key));
                    
                    $to = $this->bm->getBranchObjectByName($val);
                    $to->setMergeName($this->git->localBranchName($val));
                    
                    $result = $this->git->merge($from, $to, $this->options['pull-request']);
                    $pushedObjects[] = $result;
                } else {
                    $to = $this->bm->getBranchObjectByName($key);
                    $to->setMergeName($this->git->localBranchName($key));
                    
                    $result = $this->git->merge($unmerged, $to, $this->options['pull-request']);
                    
                    $pushedObjects[] = $result;
                    
                    $to = $this->bm->getBranchObjectByName($val);
                    $to->setMergeName($this->git->localBranchName($val));
                    
                    $result = $this->git->merge($result, $to, $this->options['pull-request']);
                    $pushedObjects[] = $result;
                }
            }
        }
        
        if ($this->options['debug']) {
            foreach ($pushedObjects as $branchObject) {
                echo "Debug mode on: Deleting {$branchObject}" . PHP_EOL;
                $this->git->remoteDelete($branchObject);
                unset($pushedObjects["{$branchObject}"]);
            }
        }
        
        return $pushedObjects;
    }
}
