<?php declare(strict_types=1);
/*
 * This file is part of Phive.
 *
 * Copyright (c) Arne Blankerts <arne@blankerts.de>, Sebastian Heuer <sebastian@phpeople.de> and contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
namespace PharIo\Phive;

use const CURLE_COULDNT_RESOLVE_HOST;
use const DNS_A;
use const DNS_AAAA;
use function array_merge;
use function array_shift;
use function count;
use function dns_get_record;
use function sprintf;
use Exception;
use PharIo\Phive\Cli\Output;

class RingdownCurlHttpClient implements HttpClient {
    /** @var HttpClient */
    private $client;

    /** @var CurlConfig */
    private $config;

    /** @var Output */
    private $output;

    /** @var array */
    private $resolved = [];

    public function __construct(HttpClient $client, CurlConfig $config, Output $output) {
        $this->client = $client;
        $this->config = $config;
        $this->output = $output;
    }

    public function get(Url $url, ?ETag $etag = null): HttpResponse {
        return $this->execWrapper('get', $url, $etag);
    }

    public function head(Url $url, ?ETag $etag = null): HttpResponse {
        return $this->execWrapper('head', $url, $etag);
    }

    private function execWrapper(string $method, Url $url, ?ETag $etag): HttpResponse {
        $hostname = $url->getHostname();
        $response = null;

        foreach ($this->resolveHostname($hostname) as $ip) {
            $this->config->setResolvedIp($hostname, $ip);
            $this->output->writeInfo(sprintf('Trying to connect to %s (%s)', $hostname, $ip));

            try {
                $response = $this->client->{$method}($url, $etag);

                /** @var HttpResponse $response */
                if ($response->isSuccess()) {
                    return $response;
                }
            } catch (HttpException $e) {
                $this->output->writeError(sprintf('Request failed: %s', $e->getMessage()));
            }

            if ($response !== null && !$response->isNotFound()) {
                $this->removeUnavailable($hostname);
            }
        }

        if ($response === null) {
            throw new HttpException('No mirror yielded any result. Giving up.');
        }

        return $response;
    }

    private function resolveHostname(string $hostname): array {
        if (!isset($this->resolved[$hostname])) {
            $ipList = array_merge(
                $this->queryDNS($hostname, DNS_A),
                $this->queryDNS($hostname, DNS_AAAA)
            );

            if (!count($ipList)) {
                throw new HttpException(
                    sprintf('DNS Problem: Did not find any IP for hostname "%s"', $hostname),
                    CURLE_COULDNT_RESOLVE_HOST
                );
            }

            $this->resolved[$hostname] = $ipList;
        }

        return $this->resolved[$hostname];
    }

    private function queryDNS(string $hostname, int $type): array {
        $ipList = [];

        try {
            foreach (dns_get_record($hostname, $type) as $result) {
                $ipList[] = $result[$type === DNS_A ? 'ip' : 'ipv6'];
            }
        } catch (Exception $e) {
        }

        return $ipList;
    }

    private function removeUnavailable(string $hostname): void {
        array_shift($this->resolved[$hostname]);
    }
}
