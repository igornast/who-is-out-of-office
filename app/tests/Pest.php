<?php


use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

uses(WebTestCase::class)->beforeAll(fn () => self::ensureKernelShutdown())->in('Functional');

function createClient(WebTestCase $context): KernelBrowser
{
    $reflection = new ReflectionMethod($context, 'createClient');
    $reflection->setAccessible(true);
    $client = $reflection->invoke($context);
    $client->insulate();

    return $client;
}
