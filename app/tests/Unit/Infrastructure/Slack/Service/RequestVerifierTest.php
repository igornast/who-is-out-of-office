<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Service\RequestVerifier;
use Symfony\Component\HttpFoundation\Request;

beforeEach(function (): void {
    $this->signingSecret = 'test-signing-secret';

    $this->verifier = new RequestVerifier(signingSecret: $this->signingSecret);
});

it('returns true for valid request', function () {
    $timestamp = (string) time();
    $body = 'payload=test-body';

    $sigBaseString = sprintf('v0:%s:%s', $timestamp, $body);
    $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->signingSecret);

    $request = Request::create('/api/slack', 'POST', content: $body);
    $request->headers->set('x-slack-request-timestamp', $timestamp);
    $request->headers->set('x-slack-signature', $signature);

    expect($this->verifier->isValid($request))->toBeTrue();
});

it('returns false when timestamp header is missing', function () {
    $request = Request::create('/api/slack', 'POST', content: 'body');
    $request->headers->set('x-slack-signature', 'v0=abc123');

    expect($this->verifier->isValid($request))->toBeFalse();
});

it('returns false when signature header is missing', function () {
    $request = Request::create('/api/slack', 'POST', content: 'body');
    $request->headers->set('x-slack-request-timestamp', (string) time());

    expect($this->verifier->isValid($request))->toBeFalse();
});

it('returns false when timestamp is too old', function () {
    $timestamp = (string) (time() - 400);
    $body = 'payload=test-body';

    $sigBaseString = sprintf('v0:%s:%s', $timestamp, $body);
    $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->signingSecret);

    $request = Request::create('/api/slack', 'POST', content: $body);
    $request->headers->set('x-slack-request-timestamp', $timestamp);
    $request->headers->set('x-slack-signature', $signature);

    expect($this->verifier->isValid($request))->toBeFalse();
});

it('returns false for invalid signature', function () {
    $timestamp = (string) time();

    $request = Request::create('/api/slack', 'POST', content: 'body');
    $request->headers->set('x-slack-request-timestamp', $timestamp);
    $request->headers->set('x-slack-signature', 'v0=invalid-signature');

    expect($this->verifier->isValid($request))->toBeFalse();
});

it('returns false for signature with wrong secret', function () {
    $timestamp = (string) time();
    $body = 'payload=test-body';

    $sigBaseString = sprintf('v0:%s:%s', $timestamp, $body);
    $signature = 'v0='.hash_hmac('sha256', $sigBaseString, 'wrong-secret');

    $request = Request::create('/api/slack', 'POST', content: $body);
    $request->headers->set('x-slack-request-timestamp', $timestamp);
    $request->headers->set('x-slack-signature', $signature);

    expect($this->verifier->isValid($request))->toBeFalse();
});
