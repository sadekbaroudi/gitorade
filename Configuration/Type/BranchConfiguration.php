<?php

namespace Sadekbaroudi\Gitorade\Configuration\Type;

use Sadekbaroudi\Gitorade\Configuration\ConfigurationAbstract;

class BranchConfiguration extends ConfigurationAbstract
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
            	       'remotes/origin/ibm_r12_hotfix2' => 'remotes/origin/ibm_r13',
            	   ),
                ),
            ),
        );
    }
}
