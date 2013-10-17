<?php

namespace Sadekbaroudi\Gitorade\Configuration;

use Symfony\Component\Config\Definition\ConfigurationInterface;

interface GitoradeConfigurationInterface extends ConfigurationInterface {
    
    public function getConfigFilePath();
    
    public function getDefaultConfig();
    
}
