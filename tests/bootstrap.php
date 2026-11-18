<?php

use Illuminate\Container\Container;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';

$application = require dirname(__DIR__).'/bootstrap/app.php';

TestEnvironment::protect($application);

$application->bootstrapWith([
    LoadEnvironmentVariables::class,
    LoadConfiguration::class,
]);

Container::setInstance(null);
unset($application);
