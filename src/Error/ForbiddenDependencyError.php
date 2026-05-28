<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Error;

use ShipMonk\Modulint\Module;

final class ForbiddenDependencyError
{

    public function __construct(
        public readonly string $sourcePath,
        public readonly Module $sourceModule,
        public readonly string $dependencyPath,
        public readonly Module $dependencyModule,
    )
    {
    }

}
