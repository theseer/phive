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
use PharIo\Phive\Cli\Options;
use PharIo\Version\AnyVersionConstraint;
use PharIo\Version\ExactVersionConstraint;
use PharIo\Version\Version;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * @covers \PharIo\Phive\InstallCommandConfig
 */
class InstallCommandConfigTest extends TestCase {
    use ScalarTestDataProvider;

    public function testGetTargetDirectory(): void {
        $directory = $this->getDirectoryMock();
        $locator   = $this->getTargetDirectoryLocatorMock();
        $locator->method('getTargetDirectory')->willReturn($directory);

        $commandConfig = new InstallCommandConfig(
            $this->getOptionsMock(),
            $this->getPhiveXmlConfigMock(),
            $this->getEnvironmentMock(),
            $locator
        );

        $this->assertSame($directory, $commandConfig->getTargetDirectory());
    }

    public function testGetRequestedPharsFromPhiveXmlConfig(): void {
        $options = $this->getOptionsMock();
        $options->expects($this->once())
            ->method('getArgumentCount')
            ->willReturn(0);

        $configuredPhar1 = new ConfiguredPhar('Some Phar', new AnyVersionConstraint());
        $configuredPhar2 = new ConfiguredPhar('Some Other Phar', new ExactVersionConstraint('1.2.3'), new Version('1.2.3'));

        $phiveXmlConfig = $this->getPhiveXmlConfigMock();
        $phiveXmlConfig->expects($this->once())
            ->method('getPhars')
            ->willReturn([$configuredPhar1, $configuredPhar2]);

        $expectedPhars = [
            new RequestedPhar(new PharAlias('Some Phar'), new AnyVersionConstraint(), new AnyVersionConstraint()),
            new RequestedPhar(new PharAlias('Some Other Phar'), new ExactVersionConstraint('1.2.3'), new ExactVersionConstraint('1.2.3'))
        ];

        $commandConfig = new InstallCommandConfig(
            $options,
            $phiveXmlConfig,
            $this->getEnvironmentMock(),
            $this->getTargetDirectoryLocatorMock()
        );
        $this->assertEquals($expectedPhars, $commandConfig->getRequestedPhars());
    }

    public function testGetRequestedPharsFromCliOptions(): void {
        $options = $this->getOptionsMock();
        $options->expects($this->any())
            ->method('getArgumentCount')
            ->willReturn(3);

        $options->expects($this->any())
            ->method('getArgument')
            ->willReturnMap([
                [0, 'https://example.com/foo-1.2.0.phar'],
                [1, 'phpunit'],
                [2, 'phpab@1.12.0']
            ]);

        $expected = [
            new RequestedPhar(new PharUrl('https://example.com/foo-1.2.0.phar'), new ExactVersionConstraint('1.2.0'), new ExactVersionConstraint('1.2.0')),
            new RequestedPhar(new PharAlias('phpunit'), new AnyVersionConstraint(), new AnyVersionConstraint()),
            new RequestedPhar(new PharAlias('phpab'), new ExactVersionConstraint('1.12.0'), new ExactVersionConstraint('1.12.0')),
        ];

        $commandConfig = new InstallCommandConfig(
            $options,
            $this->getPhiveXmlConfigMock(),
            $this->getEnvironmentMock(),
            $this->getTargetDirectoryLocatorMock()
        );
        $this->assertEquals($expected, $commandConfig->getRequestedPhars());
    }

    public function testCopyFlagOverWritesConfigOnInstall(): void {
        $options = $this->getOptionsMock();
        $options->expects($this->once())
            ->method('getArgumentCount')
            ->willReturn(0);

        $options->expects($this->exactly(2))->method('hasOption')->with('copy')->willReturn(true);

        $configuredPhar1 = new ConfiguredPhar('Some Phar', new AnyVersionConstraint());
        $configuredPhar2 = new ConfiguredPhar('Some Other Phar', new ExactVersionConstraint('1.2.3'), new Version('1.2.3'));

        $phiveXmlConfig = $this->getPhiveXmlConfigMock();
        $phiveXmlConfig->expects($this->once())
            ->method('getPhars')
            ->willReturn([$configuredPhar1, $configuredPhar2]);

        $expectedPhars = [
            new RequestedPhar(new PharAlias('Some Phar'), new AnyVersionConstraint(), new AnyVersionConstraint(), null, true),
            new RequestedPhar(new PharAlias('Some Other Phar'), new ExactVersionConstraint('1.2.3'), new ExactVersionConstraint('1.2.3'), null, true)
        ];

        $commandConfig = new InstallCommandConfig(
            $options,
            $phiveXmlConfig,
            $this->getEnvironmentMock(),
            $this->getTargetDirectoryLocatorMock()
        );
        $this->assertEquals($expectedPhars, $commandConfig->getRequestedPhars());
    }

    /**
     * @dataProvider boolProvider
     *
     * @param bool $switch
     */
    public function testDoNotAddToPhiveXml($switch): void {
        $options = $this->getOptionsMock();
        $options->expects($this->once())
            ->method('hasOption')
            ->with('temporary')
            ->willReturn($switch);

        $config = new InstallCommandConfig(
            $options,
            $this->getPhiveXmlConfigMock(),
            $this->getEnvironmentMock(),
            $this->getTargetDirectoryLocatorMock()
        );

        $this->assertSame($switch, $config->doNotAddToPhiveXml());
    }

    /**
     * @dataProvider boolProvider
     *
     * @param bool $switch
     */
    public function testWithExtension($switch): void {
        $options = $this->getOptionsMock();
        $options->expects($this->once())
            ->method('hasOption')
            ->with('extension')
            ->willReturn($switch);

        $config = new InstallCommandConfig(
            $options,
            $this->getPhiveXmlConfigMock(),
            $this->getEnvironmentMock(),
            $this->getTargetDirectoryLocatorMock()
        );

        $this->assertSame($switch, $config->withExtension());
    }

    public function testConvertsRequestedPharAliasToLowercase(): void {
        $options = $this->getOptionsMock();
        $options->expects($this->any())
            ->method('getArgumentCount')
            ->willReturn(2);

        $options->expects($this->any())
            ->method('getArgument')
            ->willReturnMap([
                [0, 'PHPUNIT'],
                [1, 'theseer/AUTOLOAD']
            ]);

        $expected = [
            new RequestedPhar(new PharAlias('phpunit'), new AnyVersionConstraint(), new AnyVersionConstraint()),
            new RequestedPhar(new PharAlias('theseer/autoload'), new AnyVersionConstraint(), new AnyVersionConstraint())
        ];

        $commandConfig = new InstallCommandConfig(
            $options,
            $this->getPhiveXmlConfigMock(),
            $this->getEnvironmentMock(),
            $this->getTargetDirectoryLocatorMock()
        );
        $this->assertEquals($expected, $commandConfig->getRequestedPhars());
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject|TargetDirectoryLocator
     */
    private function getTargetDirectoryLocatorMock() {
        return $this->createMock(TargetDirectoryLocator::class);
    }

    /**
     * @return Directory|PHPUnit_Framework_MockObject_MockObject
     */
    private function getDirectoryMock() {
        return $this->createMock(Directory::class);
    }

    /**
     * @return Options|PHPUnit_Framework_MockObject_MockObject
     */
    private function getOptionsMock() {
        return $this->createMock(Options::class);
    }

    /**
     * @return PhiveXmlConfig|PHPUnit_Framework_MockObject_MockObject
     */
    private function getPhiveXmlConfigMock() {
        return $this->createMock(PhiveXmlConfig::class);
    }

    /**
     * @return Environment|PHPUnit_Framework_MockObject_MockObject
     */
    private function getEnvironmentMock() {
        return $this->createMock(Environment::class);
    }
}
