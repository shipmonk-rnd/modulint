<?php declare(strict_types = 1);

namespace ShipMonk\Modulint;

use ShipMonk\Modulint\Error\ForbiddenDependencyError;
use ShipMonk\Modulint\Error\MissingModuleError;
use ShipMonk\Modulint\Error\UncoveredDependencyError;
use ShipMonk\Modulint\Error\UnusedDependencyError;
use function count;

final class Result
{

    /**
     * @param array<string, array<string, list<ForbiddenDependencyError>>> $forbiddenDependencyErrors indexed by [sourceModule][targetModule][#]
     * @param array<string, list<UncoveredDependencyError>> $uncoveredDependencyErrors indexed by [sourceModule][#]
     * @param array<string, list<MissingModuleError>> $missingModuleErrors indexed by [directory][#]
     * @param array<string, list<UnusedDependencyError>> $unusedDependencyErrors indexed by [sourceModule][#]
     */
    public function __construct(
        public readonly array $forbiddenDependencyErrors,
        public readonly array $uncoveredDependencyErrors,
        public readonly array $missingModuleErrors,
        public readonly array $unusedDependencyErrors,
    )
    {
    }

    public function isSuccessful(): bool
    {
        return count($this->forbiddenDependencyErrors) === 0
            && count($this->uncoveredDependencyErrors) === 0
            && count($this->missingModuleErrors) === 0
            && count($this->unusedDependencyErrors) === 0;
    }

}
