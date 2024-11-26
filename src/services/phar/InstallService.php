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

use function sprintf;
use PharIo\FileSystem\Filename;
use PharIo\Version\AndVersionConstraintGroup;
use PharIo\Version\AnyVersionConstraint;
use PharIo\Version\GreaterThanOrEqualToVersionConstraint;
use PharIo\Version\SpecificMajorVersionConstraint;
use PharIo\Version\Version;
use PharIo\Version\VersionConstraint;

class InstallService {
    /** @var PhiveXmlConfig */
    private $phiveXml;

    /** @var PharInstaller */
    private $installer;

    /** @var PharRegistry */
    private $registry;

    /** @var PharService */
    private $pharService;

    /** @var CompatibilityService */
    private $compatibilityService;

    public function __construct(
        PhiveXmlConfig $phiveXml,
        PharInstaller $installer,
        PharRegistry $registry,
        PharService $pharService,
        CompatibilityService $compatibilityChecker
    ) {
        $this->phiveXml             = $phiveXml;
        $this->installer            = $installer;
        $this->registry             = $registry;
        $this->pharService          = $pharService;
        $this->compatibilityService = $compatibilityChecker;
    }

    public function execute(SupportedRelease $release, RequestedPhar $requestedPhar, Filename $destination, bool $updatePhiveXml, bool $skipSignatureVerification = false): void {
        $versionConstraint = $requestedPhar->getVersionConstraint();
        $makeCopy          = $requestedPhar->makeCopy();
        $phar              = $this->pharService->getPharFromRelease($release, $skipSignatureVerification);

        if (!$this->compatibilityService->canRun($phar)) {
            return;
        }

        $this->installer->install($phar->getFile(), $destination, $makeCopy);
        $this->registry->addUsage($phar, $destination);

        if ($updatePhiveXml) {
            $this->phiveXml->addPhar(
                new InstalledPhar(
                    $phar->getName(),
                    $release->getVersion(),
                    $this->getInstalledVersionConstraint($versionConstraint, $release->getVersion()),
                    $destination,
                    $makeCopy
                ),
                $requestedPhar
            );
        }
    }

    private function getInstalledVersionConstraint(VersionConstraint $requestedVersionConstraint, Version $installedVersion): VersionConstraint {
        if (!$requestedVersionConstraint instanceof AnyVersionConstraint) {
            return $requestedVersionConstraint;
        }

        $major = $installedVersion->getMajor()->getValue() ?? 0;

        return new AndVersionConstraintGroup(
            sprintf('^%s', $installedVersion->getVersionString()),
            [
                new GreaterThanOrEqualToVersionConstraint($installedVersion->getVersionString(), $installedVersion),
                new SpecificMajorVersionConstraint($installedVersion->getVersionString(), $major)
            ]
        );
    }
}
