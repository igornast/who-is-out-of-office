<?php

declare(strict_types=1);

namespace App\Infrastructure\WhoIsOoo;

use App\Module\Feed\FeedClientInterface;
use App\Shared\DTO\Feed\FeedItemDTO;
use App\Shared\Enum\AppEnvironmentEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeedClient implements FeedClientInterface
{
    public function __construct(
        #[Autowire(env: 'APP_ENV')]
        private readonly string $environment,
        #[Autowire(env: 'WHOISOOO_FEED_URL')]
        private readonly string $feedUrl,
        #[Autowire(env: 'int:WHOISOOO_FEED_TIMEOUT_SECONDS')]
        private readonly int $timeoutSeconds,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return FeedItemDTO[]
     */
    public function fetch(): array
    {
        if ('' === $this->feedUrl) {
            $this->logger->error('[FEED][FETCH]: WHOISOOO_FEED_URL is not configured.');

            return [];
        }

        $scheme = strtolower((string) (parse_url($this->feedUrl, PHP_URL_SCHEME) ?: ''));
        if ('https' !== $scheme && $this->environment === AppEnvironmentEnum::PROD->value) {
            $this->logger->error('[FEED][FETCH]: Feed URL must use https scheme.', [
                'url' => $this->feedUrl,
            ]);

            return [];
        }

        try {
            $response = $this->httpClient->request('GET', $this->feedUrl, [
                'timeout' => $this->timeoutSeconds,
                'max_redirects' => 0,
            ]);

            if (200 !== $response->getStatusCode()) {
                $this->logger->error('[FEED][FETCH]: Non-200 from feed endpoint.', [
                    'status' => $response->getStatusCode(),
                    'url' => $this->feedUrl,
                ]);

                return [];
            }

            $payload = $response->toArray();
        } catch (ExceptionInterface|\JsonException $e) {
            $this->logger->error('[FEED][FETCH]: Failed to fetch feed.', [
                'url' => $this->feedUrl,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        if (!isset($payload['items']) || !is_array($payload['items'])) {
            $this->logger->error('[FEED][FETCH]: Malformed feed payload — missing "items".', [
                'url' => $this->feedUrl,
            ]);

            return [];
        }

        $items = [];
        foreach ($payload['items'] as $rawItem) {
            if (!is_array($rawItem) || !$this->isItemValid($rawItem)) {
                continue;
            }

            $items[] = FeedItemDTO::fromFeedJsonItem($rawItem);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isItemValid(array $item): bool
    {
        foreach (['id', 'url', 'title', 'date_published'] as $key) {
            if (!isset($item[$key]) || '' === $item[$key]) {
                $this->logger->error('[FEED][FETCH]: Skipping invalid feed item.', [
                    'reason' => 'missing_field',
                    'field' => $key,
                    'external_id' => $item['id'] ?? null,
                ]);

                return false;
            }
        }

        $url = (string) $item['url'];
        $lowerScheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: ''));
        if ('http' !== $lowerScheme && 'https' !== $lowerScheme) {
            $this->logger->error('[FEED][FETCH]: Skipping invalid feed item.', [
                'reason' => 'bad_url',
                'external_id' => $item['id'],
            ]);

            return false;
        }

        try {
            new \DateTimeImmutable((string) $item['date_published']);
        } catch (\Exception) {
            $this->logger->error('[FEED][FETCH]: Skipping invalid feed item.', [
                'reason' => 'bad_date',
                'external_id' => $item['id'],
            ]);

            return false;
        }

        return true;
    }
}
