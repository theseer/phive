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

use PharIo\FileSystem\Directory;
use PharIo\FileSystem\Filename;

class InstallCommand implements Cli\Command {
    /** @var InstallCommandConfig */
    private $config;

    /** @var InstallService */
    private $installService;

    /** @var RequestedPharResolverService */
    private $pharResolver;

    /** @var ReleaseSelector */
    private $selector;

    public function __construct(
        InstallCommandConfig $config,
        InstallService $installService,
        RequestedPharResolverService $pharResolver,
        ReleaseSelector $selector
    ) {
        $this->config         = $config;
        $this->installService = $installService;
        $this->pharResolver   = $pharResolver;
        $this->selector       = $selector;
    }

    public function execute(): void {
        $targetDirectory = $this->getConfig()->getTargetDirectory();

        $todo = $this->getConfig()->getRequestedPhars();

        if (count($todo) === 0) {
            throw new InstallationFailedException('No phars to install');
        }

        foreach ($todo as $requestedPhar) {
            $this->installRequestedPhar($requestedPhar, $targetDirectory);
        }
    }

    protected function installRequestedPhar(RequestedPhar $requestedPhar, Directory $targetDirectory): void {
        $release     = $this->resolveToRelease($requestedPhar);
        $destination = $this->getDestination($release->getUrl()->getPharName(), $requestedPhar, $targetDirectory);

        $this->installService->execute($release, $requestedPhar, $destination, !$this->getConfig()->doNotAddToPhiveXml(), $this->getConfig()->skipSignatureVerification());
    }

    protected function getConfig(): InstallCommandConfig {
        return $this->config;
    }

    private function resolveToRelease(RequestedPhar $requestedPhar): SupportedRelease {
        $repository = $this->pharResolver->resolve($requestedPhar);
        $releases   = $repository->getReleasesByRequestedPhar($requestedPhar);

        return $this->selector->select(
            $requestedPhar->getIdentifier(),
            $releases,
            $requestedPhar->getLockedVersion(),
            $this->config->forceAcceptUnsignedPhars()
        );
    }

    private function getDestination(string $pharName, RequestedPhar $requestedPhar, Directory $destination): Filename {
        if ($requestedPhar->hasLocation()) {
            return $requestedPhar->getLocation();
        }

        return $destination->file($pharName);
    }
}
