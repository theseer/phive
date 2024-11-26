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

use function in_array;
use function substr;
use PharIo\Version\InvalidVersionException;
use PharIo\Version\Version;

class GithubRepository implements SourceRepository {
    /** @var JsonData */
    private $jsonData;

    public function __construct(JsonData $json) {
        $this->jsonData = $json;
    }

    public function getReleasesByRequestedPhar(RequestedPhar $requestedPhar): ReleaseCollection {
        $releases = new ReleaseCollection();
        $name     = $requestedPhar->getAlias()->asString();

        foreach ($this->jsonData->getParsed() as $entry) {
            try {
                $version = new Version($entry['tag_name']);
            } catch (InvalidVersionException $exception) {
                // we silently ignore invalid version identifiers for now as they are
                // likely to be an arbitrary tag that erroneously got promoted to release
                continue;
            }
            $pharUrl      = null;
            $signatureUrl = [];

            foreach ($entry['assets'] as $asset) {
                $url = $asset['browser_download_url'];

                if (substr($url, -5, 5) === '.phar') {
                    $pharUrl = new PharUrl($url);

                    continue;
                }

                if (in_array(substr($url, -4, 4), ['.asc', '.sig'], true)) {
                    $signatureUrl[$url] = new Url($url);
                }
            }

            // we do seem to have a version but no phar asset - can't use?
            if (!$pharUrl instanceof Url) {
                $releases->add(
                    new UnsupportedRelease($name, $version, 'No downloadable PHAR')
                );

                continue;
            }

            // if the release has multiple signatures, use the one that's closest to the
            // name of the phar, e.g. doctum.phar.asc instead of doctum.phar.sha256.asc
            $signatureUrl = $signatureUrl[$pharUrl . '.asc']
                ?? $signatureUrl[$pharUrl . '.sig']
                ?? array_shift($signatureUrl);

            // we do have a phar but no signature, could potentially be used
            if (!$signatureUrl instanceof Url) {
                $releases->add(
                    new SupportedRelease($name, $version, $pharUrl)
                );

                continue;
            }

            $releases->add(
                // GitHub doesn't publish any hashes for the files :-(
                new SupportedRelease($name, $version, $pharUrl, $signatureUrl)
            );
        }

        return $releases;
    }
}
