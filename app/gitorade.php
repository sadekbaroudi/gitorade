<?php

require_once('vendor/autoload.php');

use Sadekbaroudi\Gitorade\Command\MergeUp;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new MergeUp);
$application->run();
