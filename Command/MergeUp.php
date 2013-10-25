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
    
    protected function mergeUp(Array $branchConfig, $unmerged = array())
    {
        $pushedBranches = array();
        
        var_dump($branchConfig);
        foreach ($branchConfig as $key => $val) {
            if (is_array($val)) {
                if (empty($unmerged)) {
                    // TODO: log this instead
                    echo "1: unmerged = {$key}" . PHP_EOL;
                    
                    // Recursive call, since we have nothing to merge yet
                    $this->mergeUp(
                        $val,
                        array('full_branch' => $key, 'shorthand' => $this->git->localBranchName($key))
                    );
                } else {
                    // TODO: log this instead
                    echo "2: {$unmerged['full_branch']} (shorthand {$unmerged['shorthand']}) to {$key}" . PHP_EOL;
                    
                    // Prepare parameter to include shorthand
                    $unmergedParam = $this->git->unexpandBranch($unmerged['full_branch']);
                    $unmergedParam['m'] = $unmerged['shorthand'];
                    
                    // Call the merge!
                    $result = $this->git->merge($unmergedParam, $this->git->unexpandBranch($key));
                    
                    // Store the pushed branches
                    $pushedBranches[] = $result;
                    
                    // Recursive call with next step, including shorthand
                    $this->mergeUp($val, array('full_branch' => $result, 'shorthand' => $this->git->localBranchName($key)));
                }
            } else {
                if (empty($unmerged)) {
                    echo "3: {$key} to {$val}" . PHP_EOL;
                    $result = $this->git->merge($this->git->unexpandBranch($key), $this->git->unexpandBranch($val));
                    $pushedBranches[] = $result;
                } else {
                    echo "4: {$unmerged['full_branch']} (shorthand {$unmerged['shorthand']}) to {$key}" . PHP_EOL;
                    
                    // Prepare parameter to include shorthand
                    $unmergedParam = $this->git->unexpandBranch($unmerged['full_branch']);
                    $unmergedParam['m'] = $unmerged['shorthand'];
                    
                    $result = $this->git->merge($unmergedParam, $this->git->unexpandBranch($key));
                    $pushedBranches[] = $result;
                    
                    // Prepare parameter to include shorthand
                    $unmergedParam = $this->git->unexpandBranch($result);
                    $unmergedParam['m'] = $this->git->localBranchName($key);
                    
                    echo "5: {$result} to {$val}" . PHP_EOL;
                    $result = $this->git->merge($unmergedParam, $this->git->unexpandBranch($val));
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
