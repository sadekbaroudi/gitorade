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
            'origin/ibm_production' => array(
        	   'origin/ibm_r11_hotfix2' => array(
            	   'origin/ibm_r12' => 'origin/ibm_r13',
                ),
            ),
        );
    }
}
