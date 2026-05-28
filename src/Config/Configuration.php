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

}
