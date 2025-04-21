<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class RequestVerifier
{
    private const string HEADER_SIGNATURE_KEY = 'x-slack-signature';
    private const string HEADER_TIMESTAMP_KEY = 'x-slack-request-timestamp';
    private const int TOLERANCE_SECONDS = 300;
    private const string VERSION_PREFIX = 'v0';

    public function __construct(
        #[Autowire(env: 'SLACK_SIGNING_SECRET')]
        private readonly string $signingSecret,
    ) {
    }

    public function isValid(Request $request): bool
    {
        $timestamp = $request->headers->get(self::HEADER_TIMESTAMP_KEY);
        $signature = $request->headers->get(self::HEADER_SIGNATURE_KEY);

        if (null === $timestamp || null === $signature) {
            return false;
        }

        $currentTime = time();
        if (abs($currentTime - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $body = $request->getContent();
        $sigBaseString = sprintf('%s:%s:%s', self::VERSION_PREFIX, $timestamp, $body);
        $computedSignature = self::VERSION_PREFIX.'='.hash_hmac('sha256', $sigBaseString, $this->signingSecret);

        if (!hash_equals($computedSignature, $signature)) {
            return false;
        }

        return true;
    }
}
