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
            'remotes/origin/ibm_production' => array(
        	   'remotes/origin/ibm_r12' => array(
            	   'remotes/origin/ibm_r12_hotfix1' => array(
            	       'remotes/origin/ibm_r12_hotfix1' => 'remotes/origin/ibm_r12_hotfix2',
            	   ),
                ),
            ),
        );
    }
}
