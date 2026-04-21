<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

function test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true));
    }
}

function test_assert_equals(float|int|string|array $expected, float|int|string|array $actual, string $message): void
{
    if ($expected != $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true));
    }
}

function test_assert_throws(callable $callback, string $expectedClass, string $messageContains = ''): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if (!$throwable instanceof $expectedClass) {
            throw new RuntimeException('Unexpected exception class: ' . $throwable::class);
        }

        if ($messageContains !== '' && !str_contains($throwable->getMessage(), $messageContains)) {
            throw new RuntimeException('Exception message did not contain expected text. Got: ' . $throwable->getMessage());
        }

        return;
    }

    throw new RuntimeException('Expected exception was not thrown: ' . $expectedClass);
}

function test_invoke_method(object $object, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $arguments);
}
