<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Fixtures\ModuleA;

use ShipMonk\Modulint\Fixtures\ModuleB\ClassB;

class ClassA
{

    public function useClassB(): ClassB
    {
        return new ClassB();
    }

}
