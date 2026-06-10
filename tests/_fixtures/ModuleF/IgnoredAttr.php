<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Fixtures\ModuleF;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class IgnoredAttr
{

    public function __construct(public readonly string $className)
    {
    }

}
