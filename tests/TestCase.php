<?php

declare(strict_types=1);

namespace Jorgebyte\AntiBugPing\tests;

abstract class TestCase
{
    protected function assertTrue(bool $condition, string $message = 'Expected condition to be true.') : void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = '') : void
    {
        if ($expected === $actual) {
            return;
        }

        $default = 'Assertion failed. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.';
        throw new \RuntimeException($message !== '' ? $message : $default);
    }

    protected function assertGreaterThan(float|int $threshold, float|int $actual, string $message = '') : void
    {
        if ($actual > $threshold) {
            return;
        }

        $default = 'Assertion failed. Expected ' . var_export($actual, true) . ' to be greater than ' . var_export($threshold, true) . '.';
        throw new \RuntimeException($message !== '' ? $message : $default);
    }
}

