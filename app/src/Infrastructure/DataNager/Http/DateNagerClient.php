<?php

declare(strict_types=1);

namespace App\Infrastructure\DataNager\Http;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DateNagerClient
{
    private const API_URL = 'https://date.nager.at/api/v3/PublicHolidays';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<int, array{date: string, localName: string, name: string, countryCode: string}>
     */
    public function fetchHolidays(string $countryCode, int $year): array
    {
        $url = sprintf('%s/%d/%s', self::API_URL, $year, strtoupper($countryCode));

        try {
            $response = $this->httpClient->request('GET', $url);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Failed to fetch public holidays from Nager.Date.', 0, $e);
        }

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('API returned status code %d', $response->getStatusCode()));
        }

        return $response->toArray();
    }
}
