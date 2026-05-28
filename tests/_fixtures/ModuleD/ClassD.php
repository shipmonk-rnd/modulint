<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Fixtures\ModuleD;

use ShipMonk\Modulint\Fixtures\ModuleA\ClassA;

class ClassD
{

    public function useClassA(): ClassA
    {
        return new ClassA();
    }

}
