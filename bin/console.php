<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$args = $argv;
unset($args[0]);
exit((new \HbLib\ChargeCalc\Runner())->run(array_values($args)));