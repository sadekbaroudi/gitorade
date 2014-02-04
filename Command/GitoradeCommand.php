<?php

namespace Sadekbaroudi\Gitorade\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Sadekbaroudi\Gitorade\Gitorade;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Sadekbaroudi\Gitorade\Branches\BranchManager;

class GitoradeCommand extends Command {
    
    protected $git;
    
    protected $options;
    
    protected $dialog;
    
    protected $input;
    
    protected $output;
    
    protected $bm;
    
    public function __construct($name = null)
    {
        $this->setBranchManager(new BranchManager());
        parent::__construct($name);
    }
    
    public function setBranchManager($bm)
    {
        $this->bm = $bm;
    }
    
    protected function setDialog($dialog)
    {
        $this->dialog = $dialog;
    }
    
    protected function setInput($input)
    {
        $this->input = $input;
    }
    
    protected function setOutput($output)
    {
        $this->output = $output;
    }
    
    protected function getOutput()
    {
        return $this->output;
    }
    
    protected function getInput()
    {
        return $this->input;
    }
       
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->options = $input->getOptions();
        
        $this->setInput($input);
        $this->setOutput($output);
        
        $this->git = new Gitorade($this->getContainer());
        
        $this->setDialog($this->getHelperSet()->get('dialog'));
    }
    
    protected function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . "/../app"));
        $loader->load('services.yml');
    
        return $container;
    }
}