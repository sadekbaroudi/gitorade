<?php

namespace Sadekbaroudi\Gitorade\Configuration\Type;

use Sadekbaroudi\Gitorade\Configuration\ConfigurationInterface;

class GithubConfiguration implements ConfigurationInterface
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
