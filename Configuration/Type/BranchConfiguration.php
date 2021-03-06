<?php

namespace Sadekbaroudi\Gitorade\Configuration\Type;

use Sadekbaroudi\Gitorade\Configuration\ConfigurationAbstract;
use Tree\Node\Node;

class BranchConfiguration extends ConfigurationAbstract
{
    protected $rootName = 'BranchConfigurationRootNode';
    
    protected $configRootNode;
    
    public function getRootName()
    {
        return $this->rootName;
    }
    
    public function setRootNode(Node $root)
    {
        $this->configRootNode = $root;
    }
    
    public function getConfigFilePath()
    {
        return 'app/config/branches.yml';
    }
    
    public function getDefaultConfig()
    {
        return array(
            'remotes/origin/branch_version_1' => array(
                'remotes/origin/branch_version_2' => 'remotes/origin/branch_version_3',
            ),
        );
    }
    
    public function getConfig($key = NULL, $force = FALSE)
    {
        if (!is_null($key)) {
            throw new \LogicException(__CLASS__ . " does not support \$key in " . __METHOD__);
        }
        
        return $this->configRootNode;
    }
    
    public function setConfig($key, $value, $force = FALSE)
    {
        throw new \LogicException(__CLASS__ . " does not support " . __METHOD__ . ". " .
            "Use Tree\Node\Node object API to modify and setRootNode to pass that object.");
    }
    
    public function writeConfig()
    {
        if (!is_a($this->configRootNode, 'Tree\Node\Node')) {
            throw new \LogicException("The configRootNode must be a valid Tree\Node\Node");
        }
        
        //throw new \LogicException("convertTreeToConfig not implemented yet, can't write config!");
        
        $this->config = $this->convertTreeToConfig($this->configRootNode);
        
        return parent::writeConfig();
    }
    
    public function convertConfigToTree(Array $config, Node $root)
    {
        foreach ($config as $key => $value) {
            // If the key is not a string, we're on the last node
            if (is_integer($key)) {
                $node = new Node($value);
            } else {
                $node = new Node($key);
                
                if (is_array($value)) {
                    $this->convertConfigToTree($value, $node);
                } else {
                    $node->addChild(new Node($value));
                }
            }
            
            $root->addChild($node);
        }
        
        return $root;
    }
    
    public function convertTreeToConfig(Node $node)
    {
        $result = array();
        
        // If we're at the root node, we have to skip it, since it's a placeholder node
        if ($this->isRootNode($node)) {
            foreach ($node->getChildren() as $child) {
                $result = array_merge($this->convertTreeToConfig($child), $result);
            }
            return $result;
        }
        
        // Actual processing for children of the root below
        if ($node->isLeaf()) {
            return $node->getValue();
        } else {
            foreach ($node->getChildren() as $child) {
                $result[$node->getValue()] = $this->convertTreeToConfig($child);
            }
            return $result;
        }
    }
    
    public function refresh()
    {
        parent::refresh();
        $root = new Node($this->getRootName());
        $this->setRootNode($this->convertConfigToTree($this->config, $root));
    }
    
    public function __toString()
    {
        if (!is_a($this->configRootNode, 'Tree\Node\Node')) {
            throw new \LogicException(__METHOD__ . ": The configRootNode must be a valid Tree\Node\Node");
        }
        
        return $this->getTree($this->configRootNode);
    }
    
    public function getTree(Node $node, $spaces = 0)
    {
        if ($this->isRootNode($node)) {
            $string = '';
            foreach ($node->getChildren() as $child) {
                $string .= $this->getTree($child);
            }
            
            return $string;
        }
        
        $string = str_repeat("+", $spaces) . $node->getValue() . PHP_EOL;
        if ($node->isLeaf()) {
            return $string;
        } else {
            foreach ($node->getChildren() as $child) {
                $string .= $this->getTree($child, $spaces + 1);
            }
        }
        
        return $string;
    }
    
    protected function isRootNode(Node $node)
    {
        return $node->getValue() == $this->getRootName();
    }
}
