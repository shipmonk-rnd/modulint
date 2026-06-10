<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Error;

use ShipMonk\Modulint\Module;

final class OverlappingModulesError
{

    public function __construct(
        public readonly string $filePath,
        public readonly Module $moduleA,
        public readonly Module $moduleB,
    )
    {
    }

}
