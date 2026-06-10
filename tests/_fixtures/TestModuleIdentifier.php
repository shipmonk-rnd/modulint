<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Fixtures;

use ShipMonk\Modulint\ModuleIdentifier;

enum TestModuleIdentifier: string implements ModuleIdentifier
{

    case ModuleA = 'ModuleA';
    case ModuleB = 'ModuleB';
    case ModuleC = 'ModuleC';
    case ModuleD = 'ModuleD';
    case ModuleE = 'ModuleE';
    case ModuleF = 'ModuleF';
    case ModuleX = 'ModuleX';
    case ParentAB = 'ParentAB';

}
