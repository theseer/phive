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

class PharService {
    /** @var PharRegistry */
    private $registry;

    /** @var PharDownloader */
    private $downloader;

    public function __construct(PharRegistry $registry, PharDownloader $downloader) {
        $this->registry   = $registry;
        $this->downloader = $downloader;
    }

    public function getPharFromRelease(SupportedRelease $release, bool $skipSignatureVerification = false): Phar {
        if ($this->registry->hasPhar($release->getName(), $release->getVersion())) {
            return $this->registry->getPhar($release->getName(), $release->getVersion());
        }

        return $this->registry->addPhar(
            $this->downloader->download($release, $skipSignatureVerification)
        );
    }
}
