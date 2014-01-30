<?php

require_once('vendor/autoload.php');

use Sadekbaroudi\Gitorade\Command\MergeUp;
use Symfony\Component\Console\Application;
use Sadekbaroudi\Gitorade\Command\PullRequest;

$application = new Application('gitorade');
$application->add(new MergeUp());
$application->add(new PullRequest());
try {
    $application->run();
} catch (Exception $e) {
    echo $e->getTraceAsString();
    throw $e;
}
