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
use PharIo\Phive\Cli\GeneralContext;

class InstallContext extends GeneralContext {
    public function requiresValue(string $option): bool {
        return in_array($option, ['target', 'trust-gpg-keys'], true);
    }

    protected function getKnownOptions(): array {
        return [
            'target'                => 't',
            'copy'                  => 'c',
            'extension'             => 'e',
            'global'                => 'g',
            'temporary'             => false,
            'trust-gpg-keys'        => false,
            'force-accept-unsigned' => false
        ];
    }

    protected function getConflictingOptions(): array {
        return [
            ['global' => 'temporary'],
            ['global' => 'target']
        ];
    }
}
