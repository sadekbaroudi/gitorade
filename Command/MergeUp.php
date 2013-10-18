<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Sadekbaroudi\Gitorade\Configuration\Config;
use Sadekbaroudi\Gitorade\Configuration\Type\BranchConfiguration;

class MergeUp extends Command
{
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
               'metadata',
               'm',
               InputOption::VALUE_REQUIRED,
               'Path to the metadata that contains the branch structure'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options = $input->getOptions();
        
        $config = new Config(new BranchConfiguration());
        $output->writeln(var_export($config->getConfig(), true));
        $output->writeln(var_export($config->setConfig('ibm_production.ibm_r11_hotfix2.ibm_r12.ibm_r13', 'ibm_r20'), true));
        $output->writeln(var_export($config->writeConfig(), true));
    }
}
