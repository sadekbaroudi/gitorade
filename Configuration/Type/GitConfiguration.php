<?php

namespace Sadekbaroudi\Gitorade\Configuration\Type;

use Sadekbaroudi\Gitorade\Configuration\ConfigurationInterface;

class GitConfiguration implements ConfigurationInterface
{
    public function getConfigFilePath()
    {
        return 'app/config/git.yml';
    }
    
    public function getDefaultConfig()
    {
        return array(
            'repository' => 'git@github.com:cpliakas/git-wrapper.git',
            'directory' => '/tmp/git/git-wrapper',
            'user.name' => 'User Name',
            'user.email' => 'user@example.com',
            'gitBinary' => '/usr/bin/git',
            'alias' => 'origin',
            'push_alias' => 'userfork',
        );
    }
}
