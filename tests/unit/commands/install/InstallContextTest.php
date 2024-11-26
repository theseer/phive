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

use PHPUnit\Framework\TestCase;

/**
 * @covers \PharIo\Phive\InstallContext
 */
class InstallContextTest extends TestCase {
    /**
     * @dataProvider knownOptionCharProvider
     *
     * @param string $optionChar
     */
    public function testHasOptionForChar($optionChar): void
    {
        $context = new InstallContext();
        self::assertTrue($context->hasOptionForChar($optionChar));
    }

    /**
     * @dataProvider knowsOptionProvider
     *
     * @param string $option
     */
    public function testKnowsOption($option): void
    {
        $context = new InstallContext();
        self::assertTrue($context->knowsOption($option));
    }

    public function knowsOptionProvider(): array {
        return [
            ['extension'],
        ];
    }

    public function knownOptionCharProvider(): array {
        return [
            ['e'],
        ];
    }
}
