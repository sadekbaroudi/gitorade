<?php

namespace Sadekbaroudi\Gitorade\Configuration;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Config\Definition\Processor;
use Sadekbaroudi\Gitorade\Configuration\GitoradeConfigurationInterface;

class Config {
    
    public function __construct()
    {
        
    }
    
    public function loadConfig($interface)
    {
        $config = Yaml::parse($interface->getConfigFilePath());
        
        if ($config == $interface->getConfigFilePath()) {
            $config = $interface->getDefaultConfig();
        }
        
        $processor = new Processor();
        $configuration = new BranchConfiguration();
        $processedConfiguration = $processor->processConfiguration(
            $interface,
            array($config)
        );
        
        return $processedConfiguration;
    }
}
