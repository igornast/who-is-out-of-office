<?php

declare(strict_types=1);

namespace App\Infrastructure\WhoIsOoo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class HomepageUrlProvider
{
    private readonly string $url;

    public function __construct(
        #[Autowire(env: 'WHOISOOO_HOMEPAGE_URL')]
        string $url,
    ) {
        $trimmed = trim($url);
        $this->url = $trimmed;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function __toString(): string
    {
        return $this->url;
    }

    /**
     * @param array<string, scalar> $params
     */
    public function withQuery(array $params): string
    {
        if ('' === $this->url || [] === $params) {
            return $this->url;
        }

        $parts = parse_url($this->url);
        if (false === $parts) {
            return $this->url;
        }

        $existing = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $existing);
        }
        $merged = array_merge($existing, $params);

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = '?'.http_build_query($merged);
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.$host.$port.$path.$query.$fragment;
    }

    public function getSidebarUrl(): string
    {
        return $this->withQuery(['utm_source' => 'app', 'utm_medium' => 'sidebar']);
    }
}
