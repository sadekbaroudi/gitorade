<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Sadekbaroudi\Gitorade\Configuration\Config;
use Sadekbaroudi\Gitorade\Configuration\BranchConfiguration;

class MergeUp extends Command
{
    protected $filesystem;
    
    public function __construct($name = NULL)
    {
        $this->filesystem = new FileSystem();
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
        $this->validateOptions($options);
        
        $config = new Config();
        var_dump($config->loadConfig(new BranchConfiguration()));
        
        $output->writeln($options['metadata']);
    }
    
    protected function validateOptions($options)
    {
        if (!$this->filesystem->exists($options['metadata'])) {
            throw new \RuntimeException("metadata file must exist");
        }
        
        if (!is_file($options['metadata'])) {
            throw new \RuntimeException("metadata file must be a file");
        }
        
        
    }
}
