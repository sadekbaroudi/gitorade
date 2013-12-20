<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Sadekbaroudi\Gitorade\Command;
use GitWrapper\GitException;
use Sadekbaroudi\Gitorade\Branches\BranchManager;
use Sadekbaroudi\Gitorade\Gitorade;
use Sadekbaroudi\Gitorade\Configuration\Type\BranchConfiguration;
use Tree\Node\Node;

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
        
        $this->git = new Gitorade($this->getContainer());
        $this->git->initialize();
        
        $this->config = new BranchConfiguration();
        
        $pushedObjects = $this->mergeUp($this->config->getConfig());
        
        // If we have debug mode on, we delete the branch!
        if ($this->options['debug']) {
            $this->deleteBranches($pushedObjects);
        }
    }
    
    protected function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . "/../app"));
        $loader->load('services.yml');
        
        return $container;
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
            
            $pushedObject = $this->git->merge($from, $to);
            
            // We want to merge the local branch into it's children, since the pull request will
            //    not have been merged immediately
            $child->setValue((string)$pushedObject);
            
            if ($this->options['pull-request']) {
                $this->git->submitPullRequest($pushedObject);
            }
            
            $pushedObjects[] = $pushedObject;
            
            $pushedObjects = $this->mergeUp($child, $pushedObject->getMergeName(), $pushedObjects);
        }
        
        return $pushedObjects;
    }
    
    protected function deleteBranches($pushedObjects) {
        foreach ($pushedObjects as $branchObject) {
            echo "Debug mode on: Deleting {$branchObject}" . PHP_EOL;
            $this->git->remoteDelete($branchObject);
        }
    }
}
