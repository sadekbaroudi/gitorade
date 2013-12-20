<?php

require_once('vendor/autoload.php');

use Sadekbaroudi\Gitorade\Command\MergeUp;
use Symfony\Component\Console\Application;

$application = new Application('gitorade');
$application->add(new MergeUp());
try {
    $application->run();
} catch (Exception $e) {
    echo $e->getTraceAsString();
    throw $e;
}
