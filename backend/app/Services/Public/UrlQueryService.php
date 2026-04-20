<?php

namespace App\Services\Public;

final class UrlQueryService
{
    /**
     * @param  array<string, string|int|float|bool|null>  $params
     */
    public function mergeQuery(string $url, array $params): string
    {
        $parts = parse_url($url);
        $existing = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $existing);
        }

        $query = http_build_query(array_merge($existing, $params));

        $scheme = isset($parts['scheme']) ? "{$parts['scheme']}://" : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "{$scheme}{$host}{$port}{$path}" . ($query ? "?{$query}" : '') . $fragment;
    }
}
