<?php

require_once('vendor/autoload.php');

use Sadekbaroudi\Gitorade\Command\MergeUp;
use Symfony\Component\Console\Application;
use Sadekbaroudi\Gitorade\Command\PullRequest;
use Sadekbaroudi\Gitorade\Command\MergePullRequest;
use Sadekbaroudi\Gitorade\CustomLoader;

$customLoader = new CustomLoader();

$application = new Application('gitorade');
$application->add(new MergeUp());
$application->add(new PullRequest());
$application->add(new MergePullRequest());
foreach ($customLoader->getClasses('Command') as $class) {
    $application->add(new $class());
}
try {
    $application->run();
} catch (Exception $e) {
    echo $e->getTraceAsString();
    throw $e;
}
