<?php

namespace Sadekbaroudi\Gitorade\Configuration;

use Symfony\Component\Yaml\Yaml;

class Config {
    
    protected $config;
    
    protected $configInterface;
    
    protected $iKey;
    
    public function __construct()
    {
    }
    
    public function setInterface($configInterface)
    {
        $this->configInterface = $configInterface;
        $this->iKey = $this->getKey();
    }
    
    public function getConfig($key = NULL, $force = FALSE)
    {
        $this->loadIt($force);
        
        if (is_null($key)) {
            return $this->config[$this->iKey];
        } else {
            return isset($this->config[$this->iKey][$key]) ? $this->config[$this->iKey][$key] : NULL;
        }
    }
    
    protected function checkInitialized()
    {
        if (!isset($this->configInterface)) {
            throw new \LogicException("configInterface undefined!");
        }
    }
    
    /**
     * Set config values
     * 
     * @param string $key Key to use, can be multi-dimensional, separated by "."
     * @param mixed $value Set the value to any scalar value
     */
    public function setConfig($key, $value, $force = FALSE)
    {
        $this->loadIt($force);
        
        $keysArray = explode('.', $key);
        
        if (count($keysArray) == 1) {
            $this->config[$this->iKey][$key] = $value;
        } else {
            // Dot notation update through references
            $tmp = &$this->config[$this->iKey];
            foreach(explode('.', $key) as $k) {
                $tmp = &$tmp[$k];
            }
            $tmp = $value;
        }
    }
    
    public function writeConfig()
    {
        $this->checkInitialized();
        
        if (!isset($this->config[$this->iKey])) {
            throw new \LogicException("Config has not been loaded, cannot write file");
            return FALSE;
        }
        
        $written = file_put_contents($this->configInterface->getConfigFilePath(), Yaml::dump($this->config[$this->iKey]));
        
        if ($written === FALSE) {
            throw new \LogicException("Could not write config to file");
            return FALSE;
        }
        
        return true;
    }
    
    protected function loadIt($force = FALSE)
    {
        $this->checkInitialized();
        
        if (!isset($this->config[$this->iKey]) || $force) {
            $this->config[$this->iKey] = Yaml::parse($this->configInterface->getConfigFilePath());
        }
        
        if ($this->config[$this->iKey] == $this->configInterface->getConfigFilePath()) {
            $this->config[$this->iKey] = $this->configInterface->getDefaultConfig();
        }
        
        if (!isset($this->config[$this->iKey])) {
            throw new \LogicException("Could not load config for interface " . get_class($this->configInterface));
        }
    }
    
    protected function getKey()
    {
        $this->checkInitialized();
        return get_class($this->configInterface);
    }
}
