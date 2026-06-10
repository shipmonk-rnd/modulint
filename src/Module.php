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
     * @param list<string> $excludePaths Files or directories within $paths that are not part of this module
     * @param list<string> $allowedDependencyPaths Files or directories this module can always depend on, regardless of module coverage
     */
    public function __construct(
        public readonly ModuleIdentifier $identifier,
        public readonly array $paths = [],
        public readonly array $modules = [],
        public readonly ?array $dependencies = [],
        public readonly array $excludePaths = [],
        public readonly array $allowedDependencyPaths = [],
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
            $this->excludePaths,
            $this->allowedDependencyPaths,
        );
    }

    public function withExcludedPath(string $path): self
    {
        return new self(
            $this->identifier,
            $this->paths,
            $this->modules,
            $this->dependencies,
            [...$this->excludePaths, $path],
            $this->allowedDependencyPaths,
        );
    }

    public function withChild(self $module): self
    {
        return new self(
            $this->identifier,
            $this->paths,
            [...$this->modules, $module],
            $this->dependencies,
            $this->excludePaths,
            $this->allowedDependencyPaths,
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
            $this->excludePaths,
            $this->allowedDependencyPaths,
        );
    }

    public function canDependOnPath(string $path): self
    {
        return new self(
            $this->identifier,
            $this->paths,
            $this->modules,
            $this->dependencies,
            $this->excludePaths,
            [...$this->allowedDependencyPaths, $path],
        );
    }

    public function canDependOnAnything(): self
    {
        return new self(
            $this->identifier,
            $this->paths,
            $this->modules,
            null,
            $this->excludePaths,
            $this->allowedDependencyPaths,
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
