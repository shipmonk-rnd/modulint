<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Config;

use PhpParser\NodeVisitor;
use ShipMonk\Modulint\Module;

/**
 * @api
 */
final class Configuration
{

    /**
     * @var list<Module>
     */
    private array $modules = [];

    /**
     * @var list<string>
     */
    private array $fullyModulizedDirectories = [];

    /**
     * @var list<NodeVisitor>
     */
    private array $astVisitors = [];

    /**
     * @var list<string>
     */
    private array $alwaysAllowedDependencyPaths = [];

    private string $baselineFile = 'modulint-baseline.php';

    private bool $flatModulesEnforced = false;

    public function addModule(Module $module): self
    {
        $this->modules[] = $module;
        return $this;
    }

    /**
     * @param list<Module> $modules
     */
    public function addModules(array $modules): self
    {
        foreach ($modules as $module) {
            $this->modules[] = $module;
        }

        return $this;
    }

    /**
     * Directory whose files must all be covered by a module.
     * Any unmodulized file inside will be reported as missing-module error.
     */
    public function addFullyModulizedDirectory(string $relativePath): self
    {
        $this->fullyModulizedDirectories[] = $relativePath;
        return $this;
    }

    /**
     * Visitor participating in the used-symbol extraction traversal, running before the extracting visitor.
     * Names are already resolved, so the visitor sees fully qualified names.
     * Returning NodeTraverser::DONT_TRAVERSE_CHILDREN excludes the node's children from dependency analysis.
     * Visitors are reused for all analyzed files, so they should be stateless.
     */
    public function addAstVisitor(NodeVisitor $visitor): self
    {
        $this->astVisitors[] = $visitor;
        return $this;
    }

    /**
     * File or directory every module can depend on without declaring it (e.g. 'vendor').
     */
    public function addAlwaysAllowedDependencyPath(string $path): self
    {
        $this->alwaysAllowedDependencyPaths[] = $path;
        return $this;
    }

    /**
     * Path (relative to working directory) of the baseline file.
     * When the file exists, errors listed in it are ignored and its stale entries are reported.
     * Generate it with `modulint --generate-baseline`.
     */
    public function setBaselineFile(string $relativePath): self
    {
        $this->baselineFile = $relativePath;
        return $this;
    }

    /**
     * Report files that belong to multiple modules, unless one of those modules is a (transitive) child of the other.
     */
    public function enforceFlatModules(bool $enforce = true): self
    {
        $this->flatModulesEnforced = $enforce;
        return $this;
    }

    /**
     * @return list<Module>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * @return list<string>
     */
    public function getFullyModulizedDirectories(): array
    {
        return $this->fullyModulizedDirectories;
    }

    /**
     * @return list<NodeVisitor>
     */
    public function getAstVisitors(): array
    {
        return $this->astVisitors;
    }

    /**
     * @return list<string>
     */
    public function getAlwaysAllowedDependencyPaths(): array
    {
        return $this->alwaysAllowedDependencyPaths;
    }

    public function getBaselineFile(): string
    {
        return $this->baselineFile;
    }

    public function isFlatModulesEnforced(): bool
    {
        return $this->flatModulesEnforced;
    }

}
