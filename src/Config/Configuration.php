<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Config;

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
     * @var list<string>
     */
    private array $ignoredAttributes = [];

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
     * Class-like references inside arguments of listed attributes are not considered dependencies.
     * The attribute class itself still counts as a dependency.
     *
     * @param list<string> $attributeClassNames
     */
    public function ignoreUsagesInAttributes(array $attributeClassNames): self
    {
        foreach ($attributeClassNames as $attributeClassName) {
            $this->ignoredAttributes[] = $attributeClassName;
        }

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
     * @return list<string>
     */
    public function getIgnoredAttributes(): array
    {
        return $this->ignoredAttributes;
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
