<?php

namespace Sadekbaroudi\Gitorade\Configuration\Type;

use Sadekbaroudi\Gitorade\Configuration\ConfigurationAbstract;

class GithubConfiguration extends ConfigurationAbstract
{
    public function getConfigFilePath()
    {
        return 'app/config/github.yml';
    }
    
    public function getDefaultConfig()
    {
        return array(
            'token' => 'developertoken',
            'username' => 'username',
            'password' => 'password', // If using login, specify the password
        );
    }
}
