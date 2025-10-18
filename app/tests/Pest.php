<?php

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

uses(WebTestCase::class)->beforeAll(fn () => self::ensureKernelShutdown())->in('Functional');

function srcDirectory(string $path): string
{
    return dirname(__FILE__, 2).'/src'.$path;
}

function modules(): array
{
    return array_diff(scandir(srcDirectory('/Module')), ['.', '..']);
}

function createClient(WebTestCase $context): KernelBrowser
{
    $reflection = new ReflectionMethod($context, 'createClient');
    $reflection->setAccessible(true);
    $client = $reflection->invoke($context);
    $client->insulate();

    return $client;
}
