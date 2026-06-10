<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Fixtures\ModuleF;

use ShipMonk\Modulint\Fixtures\ModuleB\ClassB;

final class AttributeUser
{

    #[IgnoredAttr(ClassB::class)]
    public function doSomething(): void
    {
    }

}
