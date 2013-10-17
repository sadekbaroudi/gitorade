<?php

namespace Sadekbaroudi\Gitorade\Configuration;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class BranchConfiguration implements ConfigurationInterface
{
    public function getConfigFilePath()
    {
        return 'configs/branches';
    }
    
    public function getDefaultConfig()
    {
        return array(
        );
    }
    
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('branches');
        return $treeBuilder;
    }
}
