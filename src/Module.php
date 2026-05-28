<?php declare(strict_types = 1);

namespace ShipMonk\Modulint;

use ShipMonk\Modulint\Exception\LogicException;
use function in_array;

/**
 * @api
 */
final class Module
{

    /**
     * @param list<string> $paths
     * @param list<self> $modules Child modules whose files are also part of this module
     * @param list<ModuleIdentifier>|null $dependencies
     */
    public function __construct(
        public readonly ModuleIdentifier $identifier,
        public readonly array $paths = [],
        public readonly array $modules = [],
        public readonly ?array $dependencies = [],
    )
    {
    }

    public function withPath(string $path): self
    {
        return new self(
            $this->identifier,
            [...$this->paths, $path],
            $this->modules,
            $this->dependencies,
        );
    }

    public function withChild(self $module): self
    {
        return new self(
            $this->identifier,
            $this->paths,
            [...$this->modules, $module],
            $this->dependencies,
        );
    }

    public function canDependOn(ModuleIdentifier $dependency): self
    {
        if ($this->dependencies === null) {
            throw new LogicException('Module can already depend on anything, cannot add specific dependency');
        }

        return new self(
            $this->identifier,
            $this->paths,
            $this->modules,
            [...$this->dependencies, $dependency],
        );
    }

    public function canDependOnAnything(): self
    {
        return new self(
            $this->identifier,
            $this->paths,
            $this->modules,
            null,
        );
    }

    /**
     * @param list<self> $others
     */
    public function canDependOnAnyOf(array $others): bool
    {
        if ($this->dependencies === null) {
            return true;
        }

        foreach ($others as $other) {
            if ($this->identifier === $other->identifier
                || in_array($other->identifier, $this->dependencies, true)) {
                return true;
            }
        }

        return false;
    }

    public function isChildOf(Module $other): bool
    {
        foreach ($other->modules as $child) {
            if ($this === $child || $this->isChildOf($child)) {
                return true;
            }
        }

        return false;
    }

}
