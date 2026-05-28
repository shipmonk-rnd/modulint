<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Error;

use ShipMonk\Modulint\Module;
use ShipMonk\Modulint\ModuleIdentifier;

final class UnusedDependencyError
{

    public function __construct(
        public readonly Module $sourceModule,
        public readonly ModuleIdentifier $unusedDependency,
    )
    {
    }

}
