<?php

require_once('vendor/autoload.php');

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/*
 * I know using $GLOBALS['c'] is bad practice, but refactor this for
 * a better approach where I can access the service container from anywhere
 */
$GLOBALS['c'] = new ContainerBuilder();
$loader = new YamlFileLoader($GLOBALS['c'], new FileLocator(__DIR__));
$loader->load('services.yml');

$application = $GLOBALS['c']->get('Application');
$application->add($GLOBALS['c']->get('MergeUp'));
$application->run();
