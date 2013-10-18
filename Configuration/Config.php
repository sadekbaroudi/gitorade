<?php

namespace Sadekbaroudi\Gitorade\Configuration;

use Symfony\Component\Yaml\Yaml;

class Config {
    
    protected $config;
    
    protected $configInterface;
    
    public function __construct($configInterface)
    {
        $this->configInterface = $configInterface;
    }
    
    public function getConfig($key = NULL, $force = FALSE)
    {
        $this->loadIt($force);
        
        if (is_null($key)) {
            return $this->config;
        } else {
            return isset($this->config[$key]) ? $this->config[$key] : NULL;
        }
    }
    
    /**
     * Set config values
     * 
     * @param string $key Key to use, can be multi-dimensional, separated by "."
     * @param mixed $value Set the value to any scalar value
     */
    public function setConfig($key, $value)
    {
        $this->loadIt();
        
        $keysArray = explode('.', $key);
        
        if (count($keysArray) == 1) {
            $this->config[$key] = $value;
        } else {
            // Dot notation update through references
            $tmp = &$this->config;
            foreach(explode('.', $key) as $k) {
                $tmp = &$tmp[$k];
            }
            $tmp = $value;
        }
    }
    
    public function writeConfig()
    {
        if (!isset($this->config)) {
            throw new \LogicException("Config has not been loaded, cannot write file");
            return FALSE;
        }
        
        $written = file_put_contents($this->configInterface->getConfigFilePath(), Yaml::dump($this->config));
        
        if ($written === FALSE) {
            throw new \LogicException("Could not write config to file");
            return FALSE;
        }
        
        return true;
    }
    
    protected function loadIt($force = FALSE)
    {
        if (!isset($this->config) || $force) {
            $this->config = Yaml::parse($this->configInterface->getConfigFilePath());
        }
        
        if ($this->config == $this->configInterface->getConfigFilePath()) {
            $this->config = $this->configInterface->getDefaultConfig();
        }
        
        if (!isset($this->config)) {
            throw new \LogicException("Could not load config for interface " . get_class($this->configInterface));
        }
    }
}
