<?php
namespace PharIo\Phive;

use PharIo\FileSystem\File;

class FileDownloader {

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var CacheBackend
     */
    private $cache;

    /**
     * @param HttpClient   $httpClient
     * @param CacheBackend $cache
     */
    public function __construct(HttpClient $httpClient, CacheBackend $cache) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    /**
     * @param Url $url
     *
     * @return File
     * @throws DownloadFailedException
     */
    public function download(Url $url) {
        $cachedETag = $this->cache->hasEntry($url) ? $this->cache->getEtag($url) : null;

        try {
            $response = $this->httpClient->get($url, $cachedETag);
        } catch (HttpException $e) {
            throw new DownloadFailedException(
                sprintf(
                    'Unexpected HTTP error: %s (Code: %d)',
                    $e->getMessage(),
                    $e->getCode()
                )
            );
        }

        if (!$response->isSuccess()) {
            throw new DownloadFailedException(
                sprintf('Failed to download load %s: HTTP Code %d', $url, $response->getHttpCode()),
                $response->getHttpCode()
            );
        }

        if ($response->getHttpCode() === 304) {
            return new File($url->getFilename(), $this->cache->getContent($url));
        }

        if ($response->hasETag()) {
            $this->cache->storeEntry($url, $response->getETag(), $response->getBody());
        }

        return new File($url->getFilename(), $response->getBody());
    }

}
