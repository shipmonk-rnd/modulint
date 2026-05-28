<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Fixtures\ModuleC;

use ShipMonk\Modulint\Fixtures\External\ExternalClass;

class ClassC
{

    public function useExternalClass(): ExternalClass
    {
        return new ExternalClass();
    }

}
