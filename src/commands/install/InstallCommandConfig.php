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

use const PREG_SPLIT_NO_EMPTY;
use function count;
use function mb_strtolower;
use function preg_split;
use PharIo\FileSystem\Directory;
use PharIo\Phive\Cli\CommandOptionsException;
use PharIo\Version\AnyVersionConstraint;
use PharIo\Version\ExactVersionConstraint;
use PharIo\Version\VersionConstraint;
use PharIo\Version\VersionConstraintParser;

class InstallCommandConfig {
    /** @var Cli\Options */
    private $cliOptions;

    /** @var PhiveXmlConfig */
    private $phiveXmlConfig;

    /** @var Environment */
    private $environment;

    /** @var TargetDirectoryLocator */
    private $targetDirectoryLocator;

    public function __construct(
        Cli\Options $options,
        PhiveXmlConfig $phiveXmlConfig,
        Environment $environment,
        TargetDirectoryLocator $targetDirectoryLocator
    ) {
        $this->cliOptions             = $options;
        $this->phiveXmlConfig         = $phiveXmlConfig;
        $this->environment            = $environment;
        $this->targetDirectoryLocator = $targetDirectoryLocator;
    }

    /**
     * @throws CommandOptionsException
     * @throws ConfigException
     */
    public function getTargetDirectory(): Directory {
        if ($this->installGlobally()) {
            return $this->environment->getGlobalBinDir();
        }

        return $this->targetDirectoryLocator->getTargetDirectory();
    }

    /**
     * @throws CommandOptionsException
     * @throws ConfiguredPharException
     * @throws InstallCommandConfigException
     * @throws UnsupportedVersionConstraintException
     *
     * @return RequestedPhar[]
     */
    public function getRequestedPhars(): array {
        if ($this->cliOptions->getArgumentCount() === 0) {
            return $this->getPharsFromPhiveXmlConfig();
        }

        return $this->getPharsFromCliArguments();
    }

    public function doNotAddToPhiveXml(): bool {
        return $this->cliOptions->hasOption('temporary');
    }

    public function forceAcceptUnsignedPhars(): bool {
        return $this->cliOptions->hasOption('force-accept-unsigned');
    }

    public function withExtension(): bool {
        return $this->cliOptions->hasOption('extension');
    }

    /**
     * @throws ConfiguredPharException
     *
     * @return RequestedPhar[]
     */
    private function getPharsFromPhiveXmlConfig(): array {
        $phars = [];

        foreach ($this->phiveXmlConfig->getPhars() as $configuredPhar) {
            $location = $configuredPhar->hasLocation() ? $configuredPhar->getLocation() : null;

            $phars[] = new RequestedPhar(
                $this->getIdentifier($configuredPhar),
                $configuredPhar->getVersionConstraint(),
                $this->getVersionToInstall($configuredPhar),
                $location,
                $this->makeCopy() || $configuredPhar->isCopy()
            );
        }

        return $phars;
    }

    /**
     * @throws CommandOptionsException
     * @throws InstallCommandConfigException
     * @throws UnsupportedVersionConstraintException
     *
     * @return RequestedPhar[]
     */
    private function getPharsFromCliArguments(): array {
        $phars    = [];
        $argCount = $this->cliOptions->getArgumentCount();

        for ($i = 0; $i < $argCount; $i++) {
            $argument = $this->cliOptions->getArgument($i);

            if (Url::isUrl($argument)) {
                if (!Url::isHttpsUrl($argument)) {
                    throw new InstallCommandConfigException(
                        'Cannot install from non HTTPS URL',
                        InstallCommandConfigException::UnsupportedProtocol
                    );
                }
                $identifier        = new PharUrl($argument);
                $versionConstraint = new ExactVersionConstraint(
                    $identifier->getPharVersion()->getVersionString()
                );
            } else {
                $argumentParts = preg_split('/[@:=]/', $argument, 2, PREG_SPLIT_NO_EMPTY);
                $identifier    = new PharAlias(mb_strtolower($argumentParts[0]));

                if (count($argumentParts) === 2) {
                    $versionConstraint = (new VersionConstraintParser())->parse($argumentParts[1]);
                } else {
                    $versionConstraint = new AnyVersionConstraint();
                }
            }

            $phars[] = new RequestedPhar(
                $identifier,
                $versionConstraint,
                $versionConstraint,
                null,
                $this->makeCopy()
            );
        }

        return $phars;
    }

    private function installGlobally(): bool {
        return $this->cliOptions->hasOption('global');
    }

    private function makeCopy(): bool {
        return $this->cliOptions->hasOption('copy') || $this->installGlobally();
    }

    /**
     * @throws ConfiguredPharException
     */
    private function getIdentifier(ConfiguredPhar $configuredPhar): PharIdentifier {
        if (Url::isUrl($configuredPhar->getName())) {
            return new PharUrl($configuredPhar->getName());
        }

        if ($configuredPhar->hasUrl()) {
            return $configuredPhar->getUrl();
        }

        return new PharAlias($configuredPhar->getName());
    }

    /**
     * @throws ConfiguredPharException
     */
    private function getVersionToInstall(ConfiguredPhar $configuredPhar): VersionConstraint {
        $versionConstraint = $configuredPhar->getVersionConstraint();

        if ($configuredPhar->isInstalled() && $versionConstraint->complies($configuredPhar->getInstalledVersion())) {
            return new ExactVersionConstraint($configuredPhar->getInstalledVersion()->getVersionString());
        }

        return $versionConstraint;
    }
}
