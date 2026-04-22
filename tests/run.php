<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/ConfigParserTest.php';
require_once __DIR__ . '/ScoreEngineTest.php';

$tests = [
    new \Jorgebyte\AntiBugPing\tests\ConfigParserTest(),
    new \Jorgebyte\AntiBugPing\tests\ScoreEngineTest(),
];

$failures = [];
$total = 0;

foreach ($tests as $testInstance) {
    $ref = new ReflectionClass($testInstance);
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        ++$total;

        try {
            $method->invoke($testInstance);
            echo '[PASS] ' . $ref->getShortName() . '::' . $method->getName() . PHP_EOL;
        } catch (Throwable $throwable) {
            $failures[] = $ref->getShortName() . '::' . $method->getName() . ' -> ' . $throwable->getMessage();
            echo '[FAIL] ' . end($failures) . PHP_EOL;
        }
    }
}

if (count($failures) > 0) {
    echo PHP_EOL . 'Failed: ' . count($failures) . '/' . $total . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'All tests passed: ' . $total . PHP_EOL;

