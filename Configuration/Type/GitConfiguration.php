<?php

namespace Sadekbaroudi\Gitorade\Configuration\Type;

use Sadekbaroudi\Gitorade\Configuration\ConfigurationAbstract;

class GitConfiguration extends ConfigurationAbstract
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
            'privateKey' => '/home/sadek/.ssh/id_rsa',
            'upstream_alias' => 'origin',
            'fork_alias' => 'userfork',
        );
    }
}
