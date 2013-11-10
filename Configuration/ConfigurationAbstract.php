<?php

namespace Sadekbaroudi\Gitorade\Configuration;

use Symfony\Component\Yaml\Yaml;

abstract class ConfigurationAbstract {
    
    protected $config;
    
    public function __construct()
    {
        $this->refresh();
    }
    
    abstract public function getConfigFilePath();
    
    abstract public function getDefaultConfig();
    
    public function getConfig($key = NULL, $force = FALSE)
    {
        if ($force) {
            $this->refresh();
        }
        
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
     * @param boolean $force force the refresh before setting a value
     */
    public function setConfig($key, $value, $force = FALSE)
    {
        if ($force) {
            $this->refresh();
        }
        
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
        
        $written = file_put_contents($this->getConfigFilePath(), Yaml::dump($this->config));
        
        if ($written === FALSE) {
            throw new \LogicException("Could not write config");
            return FALSE;
        }
        
        return true;
    }
    
    protected function refresh()
    {
        $this->config = Yaml::parse($this->getConfigFilePath());
        
        if ($this->config == $this->getConfigFilePath()) {
            $this->config = $this->getDefaultConfig();
        }
        
        if (!isset($this->config)) {
            throw new \LogicException("Could not load config for interface " . get_class($this));
        }
    }
}
