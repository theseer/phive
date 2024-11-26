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
use PharIo\Version\AnyVersionConstraint;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PharIo\Phive\InstallCommandConfig
 */
class InstallCommandTest extends TestCase
{
    use ScalarTestDataProvider;

    /**
     * @dataProvider boolProvider
     *
     * @param bool $switch
     */
    public function testAddingOfExtension($switch): void
    {
        $pharName = $expectedPharName = 'some-path';
        if ($switch) {
            $expectedPharName .= '.phar';
        }

        $directory = $this->createMock(Directory::class);
        $directory
            ->expects($this->once())
            ->method('file')
            ->with($expectedPharName);

        $requestedPhars = [
            new RequestedPhar(
                new PharAlias($pharName),
                new AnyVersionConstraint(),
                new AnyVersionConstraint(),
                null,
                true
            ),
        ];

        $config = $this->createMock(InstallCommandConfig::class);
        $config
            ->method('getTargetDirectory')
            ->willReturn($directory);

        $config
            ->method('getRequestedPhars')
            ->willReturn($requestedPhars);

        $config
            ->method('withExtension')
            ->willReturn($switch);

        $installService = $this->createMock(InstallService::class);
        $sourceRepository = $this->createMock(SourceRepository::class);

        $pharResolver = $this->createMock(RequestedPharResolverService::class);
        $pharResolver
            ->method('resolve')
            ->willReturn($sourceRepository);

        $url = $this->createMock(PharUrl::class);
        $url
            ->method('getPharName')
            ->willReturn($pharName);

        $release = $this->createMock(SupportedRelease::class);
        $release
            ->method('getUrl')
            ->willReturn($url);

        $selector = $this->createMock(ReleaseSelector::class);
        $selector
            ->method('select')
            ->willReturn($release);

        $command = new InstallCommand($config, $installService, $pharResolver, $selector);
        $command->execute();
    }
}
