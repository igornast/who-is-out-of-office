<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Http;

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SlackProfileApiClient
{
    private const PROFILE_SET_URL = 'https://slack.com/api/users.profile.set';
    private const OAUTH_ACCESS_URL = 'https://slack.com/api/oauth.v2.access';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function setProfileStatus(string $token, string $slackUserId, string $text, string $emoji, int $expiresAt): void
    {
        $this->postProfile($token, [
            'user' => $slackUserId,
            'profile' => [
                'status_text' => $text,
                'status_emoji' => $emoji,
                'status_expiration' => $expiresAt,
            ],
        ]);
    }

    public function clearProfileStatus(string $token, string $slackUserId): void
    {
        $this->postProfile($token, [
            'user' => $slackUserId,
            'profile' => [
                'status_text' => '',
                'status_emoji' => '',
                'status_expiration' => 0,
            ],
        ]);
    }

    /**
     * @return array{access_token: string, user_id: string}
     */
    public function exchangeOauthCode(string $code, string $redirectUri, string $clientId, string $clientSecret): array
    {
        try {
            $response = $this->httpClient->request('POST', self::OAUTH_ACCESS_URL, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ]),
            ]);
            $data = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new SlackStatusApiException('http_error', $e);
        }

        if (true !== ($data['ok'] ?? false)) {
            throw new SlackStatusApiException((string) ($data['error'] ?? 'unknown_error'));
        }

        return [
            'access_token' => (string) ($data['authed_user']['access_token'] ?? ''),
            'user_id' => (string) ($data['authed_user']['id'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postProfile(string $token, array $payload): void
    {
        try {
            $response = $this->httpClient->request('POST', self::PROFILE_SET_URL, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
            $data = $response->toArray(false);
        } catch (ExceptionInterface|\JsonException $e) {
            throw new SlackStatusApiException('http_error', $e);
        }

        if (true !== ($data['ok'] ?? false)) {
            throw new SlackStatusApiException((string) ($data['error'] ?? 'unknown_error'));
        }
    }
}
