<?php

require_once('vendor/autoload.php');

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Sadekbaroudi\Gitorade\Command\MergeUp;
use Symfony\Component\Console\Application;

/*
 * I know using $GLOBALS['c'] is bad practice, but refactor this for
 * a better approach where I can access the service container from anywhere
 * 
 * Commenting this code out, as I'm not using the service locator. If I decide
 * to completely remove this, delete this block, and then delete services.yml
$GLOBALS['c'] = new ContainerBuilder();
$loader = new YamlFileLoader($GLOBALS['c'], new FileLocator(__DIR__));
$loader->load('services.yml');
 */

$application = new Application('gitorade');
$application->add(new MergeUp());
try {
    $application->run();
} catch (Exception $e) {
    echo $e->getTraceAsString();
    throw $e;
}
