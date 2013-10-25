<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Sadekbaroudi\Gitorade\Command;
use GitWrapper\GitException;

class MergeUp extends GitoradeCommand
{
    protected $branchConfig;
    
    protected $options;
    
    protected $debug;
    
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
               InputOption::VALUE_REQUIRED,
               'turn on debug mode by passing true'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->options = $input->getOptions();
        
        $this->debug = !empty($this->options['debug']) && $this->options['debug'] == 'true' ? TRUE : FALSE;
        
        $this->git = $GLOBALS['c']->get('Gitorade');
        $this->git->init();
        
        $this->config = $GLOBALS['c']->get('Config');
        $this->config->setInterface($GLOBALS['c']->get('BranchConfiguration'));
        
        $this->mergeUp($this->config->getConfig());
    }
    
    protected function mergeUp(Array $branchConfig, $unmerged = NULL)
    {
        $pushedBranches = array();
        
        var_dump($branchConfig);
        foreach ($branchConfig as $key => $val) {
            if (is_array($val)) {
                if (is_null($unmerged)) {
                    echo "1: unmerged = {$key}" . PHP_EOL;
                    $this->mergeUp($val, $key);
                } else {
                    echo "2: {$unmerged} to {$key}" . PHP_EOL;
                    $result = $this->git->merge($this->git->unexpandBranch($unmerged), $this->git->unexpandBranch($key));
                    $pushedBranches[] = $result;
                    $this->mergeUp($val, $result);
                }
            } else {
                if (is_null($unmerged)) {
                    echo "3: {$key} to {$val}" . PHP_EOL;
                    $result = $this->git->merge($this->git->unexpandBranch($key), $this->git->unexpandBranch($val));
                    $pushedBranches[] = $result;
                } else {
                    echo "4: {$unmerged} to {$key}" . PHP_EOL;
                    $result = $this->git->merge($this->git->unexpandBranch($unmerged), $this->git->unexpandBranch($key));
                    $pushedBranches[] = $result;
                    echo "5: {$result} to {$val}" . PHP_EOL;
                    $result = $this->git->merge($this->git->unexpandBranch($result), $this->git->unexpandBranch($val));
                    $pushedBranches[] = $result;
                }
            }
        }
        
        if ($this->debug) {
            foreach ($pushedBranches as $branchString) {
                echo "Debug mode on: Deleting {$branchString}" . PHP_EOL;
                $this->git->remoteDelete($this->getRemoteDeleteArray($branchString));
            }
        }
    }
    
    protected function getRemoteDeleteArray($branchString)
    {
        $exploded = explode('/', $branchString);
        
        if (count($exploded) != 2) {
            throw new GitException("Invalid string passed to getRemoteDeleteArray");
        }
        
        return array('a' => $exploded[0], 'b' => $exploded[1]);
    }
}
