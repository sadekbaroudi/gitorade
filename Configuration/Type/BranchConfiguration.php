<?php

namespace Sadekbaroudi\Gitorade\Configuration\Type;

use Sadekbaroudi\Gitorade\Configuration\ConfigurationInterface;

class BranchConfiguration implements ConfigurationInterface
{
    public function getConfigFilePath()
    {
        return 'app/config/branches.yml';
    }
    
    public function getDefaultConfig()
    {
        return array(
            'master',
        );
    }
}
