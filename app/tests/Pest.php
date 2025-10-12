<?php

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/*
|--------------------------------------------------------------------------
| Test Configuration
|--------------------------------------------------------------------------
*/

uses(WebTestCase::class)->beforeAll(fn () => self::ensureKernelShutdown())->in('Functional');

/*
|--------------------------------------------------------------------------
| Architecture Tests
|--------------------------------------------------------------------------
|
| Architecture tests ensure code follows architectural patterns and rules.
| These tests run faster as they only analyze code structure, not execution.
|
*/

function srcDirectory(string $path): string
{
    return dirname(__FILE__, 2).'/src'.$path;
}

function modules(): array
{
    return array_diff(scandir(srcDirectory('/Module')), ['.', '..']);
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function createClient(WebTestCase $context): KernelBrowser
{
    $reflection = new ReflectionMethod($context, 'createClient');
    $reflection->setAccessible(true);
    $client = $reflection->invoke($context);
    $client->insulate();

    return $client;
}
