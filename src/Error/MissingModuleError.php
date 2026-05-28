<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Error;

final class MissingModuleError
{

    public function __construct(
        public readonly string $directoryPath,
        public readonly string $filePath,
    )
    {
    }

}
