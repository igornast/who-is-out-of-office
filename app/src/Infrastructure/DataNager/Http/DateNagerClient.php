<?php

declare(strict_types=1);

namespace App\Infrastructure\DataNager\Http;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DateNagerClient
{
    private const API_BASE_URL = 'https://date.nager.at/api/v3';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<int, array{date: string, localName: string, name: string, countryCode: string}>
     */
    public function fetchHolidays(string $countryCode, int $year): array
    {
        $url = sprintf('%s/PublicHolidays/%d/%s', self::API_BASE_URL, $year, strtoupper($countryCode));

        /** @var array<int, array{date: string, localName: string, name: string, countryCode: string}> $result */
        $result = $this->request($url, 'Failed to fetch public holidays from Nager.Date.');

        return $result;
    }

    /**
     * @return array<int, array{countryCode: string, name: string}>
     */
    public function fetchAvailableCountries(): array
    {
        $url = sprintf('%s/AvailableCountries', self::API_BASE_URL);

        /** @var array<int, array{countryCode: string, name: string}> $result */
        $result = $this->request($url, 'Failed to fetch available countries from Nager.Date.');

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function request(string $url, string $errorMessage): array
    {
        try {
            $response = $this->httpClient->request('GET', $url);

            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('%s (HTTP %d)', $errorMessage, $response->getStatusCode()));
            }

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException($errorMessage, 0, $e);
        }
    }
}
