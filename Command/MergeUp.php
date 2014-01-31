<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Sadekbaroudi\Gitorade\Command;
use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Gitorade;
use Sadekbaroudi\Gitorade\Configuration\Type\BranchConfiguration;
use Tree\Node\Node;
use Sadekbaroudi\Gitorade\Branches\BranchMerge;

class MergeUp extends GitoradeCommand
{
    protected $branchConfig;
    
    public function __construct($name = NULL)
    {
        parent::__construct($name);
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
        parent::execute($input, $output);
        
        $this->config = new BranchConfiguration();
        
        if ($this->config->defaultWasLoaded()) {
            $this->config->writeConfig();
        }
        
        $pushedObjects = $this->mergeUp($this->config->getConfig());
        
        // If we have debug mode on, we delete the branch!
        if ($this->options['debug']) {
            $this->deleteBranches($pushedObjects);
        }
    }
    
    protected function mergeUp(Node $node, $mergeName = '', $pushedObjects = array())
    {
        // If it's a leaf, we do nothing!
        if ($node->isLeaf()) {
            return $pushedObjects;
        }
        
        // For the root node, we skip it, as it's a placeholder
        if ($node->getValue() == $this->config->getRootName()) {
            foreach ($node->getChildren() as $child) {
                $pushedObjects = $this->mergeUp($child, '', $pushedObjects);
            }
            return $pushedObjects;
        }
        
        // If it's any node with children, we merge self into those branches
        foreach ($node->getChildren() as $child) {
            // Get and prepare the from/to branches based on the node values
            $from = $this->bm->getBranchObjectByName($node->getValue());
            if (!empty($mergeName)) {
                $from->setMergeName($mergeName);
            }
            $to = $this->bm->getBranchObjectByName($child->getValue());
            
            if ($this->options['interactive']) {
                $this->dialog->askConfirmation($this->getOutput(), "Merge from {$from} to {$to}: ", FALSE);
            }
            
            $branchMerge = new BranchMerge($from, $to);
            
            $pushedObjectArr = $this->git->merge($branchMerge);
            
            // We want to merge the local branch into it's children, since the pull request will
            //    not have been merged immediately
            $child->setValue((string)$pushedObjectArr['remoteBranch']);
            
            if ($this->options['pull-request'] && !empty($pushedObjectArr['pullRequest'])) {
                if ($this->options['interactive']) {
                    $this->dialog->askConfirmation($this->getOutput(), "Submit pull request for {$pushedObjectArr['pullRequest']}: ", FALSE);
                }
                $this->git->submitPullRequest($pushedObjectArr['pullRequest']);
            }
            
            $pushedObjects[] = $pushedObjectArr;
            
            $pushedObjects = $this->mergeUp($child, $pushedObjectArr['remoteBranch']->getMergeName(), $pushedObjects);
        }
        
        return $pushedObjects;
    }
    
    protected function deleteBranches($pushedObjects) {
        foreach ($pushedObjects as $returnArray) {
            echo "Debug mode on: Deleting {$returnArray['remoteBranch']}" . PHP_EOL;
            $this->git->remoteDelete($returnArray['remoteBranch']);
        }
    }
}
