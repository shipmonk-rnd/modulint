<?php declare(strict_types = 1);

namespace ShipMonk\Modulint;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ShipMonk\Modulint\Error\ForbiddenDependencyError;
use ShipMonk\Modulint\Error\MissingModuleError;
use ShipMonk\Modulint\Error\UncoveredDependencyError;
use ShipMonk\Modulint\Error\UnusedDependencyError;
use ShipMonk\Modulint\Exception\LogicException;
use SplFileInfo;
use function class_exists;
use function count;
use function file_get_contents;
use function implode;
use function in_array;
use function interface_exists;
use function is_dir;
use function is_file;
use function preg_match;
use function realpath;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trait_exists;

final class Modulint
{

    /**
     * @param list<Module> $modules
     * @param list<string> $fullyModulizedDirectories
     */
    public function enforce(
        ClassLoader $classLoader,
        string $baseDir,
        array $modules,
        array $fullyModulizedDirectories = [],
    ): Result
    {
        $allModules = $this->collectAllModules($modules);
        $filesToModulesMap = $this->buildFilesToModulesMap($baseDir, $allModules);
        $missingModuleErrors = $this->detectMissingModules($baseDir, $filesToModulesMap, $fullyModulizedDirectories);
        $phpParser = (new ParserFactory())->createForHostVersion();

        $forbiddenDependencyErrors = [];
        $uncoveredDependencyErrors = [];

        /** @var array<string, array<string, true>> $usedDependencies keyed by [moduleName][dependencyName] */
        $usedDependencies = [];

        foreach ($filesToModulesMap as $filePath => $fileModules) {
            $modulesWithDependencies = [];

            foreach ($fileModules as $fileModule) {
                if ($fileModule->dependencies !== null) {
                    $modulesWithDependencies[] = $fileModule;
                }
            }

            if ($modulesWithDependencies === []) {
                continue;
            }

            $code = file_get_contents($filePath);

            if ($code === false) {
                throw new LogicException("Failed to read file {$filePath}");
            }

            $filePathRelative = $this->shortenPath($filePath, $baseDir);
            $usedSymbols = $this->getUsedClassLikeSymbols($phpParser, $code);

            foreach ($usedSymbols as $usedSymbol) {
                if ($this->isBuiltInSymbol($usedSymbol)) {
                    continue;
                }

                $usedSymbolPath = $this->getClassLikePath($classLoader, $usedSymbol);

                if ($usedSymbolPath === null || str_starts_with($usedSymbolPath, 'phar://')) {
                    continue;
                }

                $usedSymbolPathNormalized = $this->realPath($usedSymbolPath);
                $usedSymbolModules = $filesToModulesMap[$usedSymbolPathNormalized] ?? [];
                $usedSymbolPathRelative = null;

                foreach ($modulesWithDependencies as $fileModule) {
                    if ($usedSymbolModules !== []) {
                        if ($fileModule->dependencies === null) {
                            continue;
                        }

                        $matched = false;

                        foreach ($usedSymbolModules as $usedSymbolModule) {
                            if ($fileModule->identifier === $usedSymbolModule->identifier) {
                                $matched = true;
                            } elseif (in_array($usedSymbolModule->identifier, $fileModule->dependencies, true)) {
                                $matched = true;
                                $usedDependencies[$fileModule->identifier->name][$usedSymbolModule->identifier->name] = true;
                            }
                        }

                        if ($matched) {
                            continue;
                        }
                    }

                    $usedSymbolPathRelative ??= $this->shortenPath($usedSymbolPathNormalized, $baseDir);

                    if ($usedSymbolModules !== []) {
                        $usedSymbolModule = $usedSymbolModules[0];

                        for ($i = 1; $i < count($usedSymbolModules); $i++) {
                            if ($usedSymbolModules[$i]->isChildOf($usedSymbolModule)) {
                                $usedSymbolModule = $usedSymbolModules[$i];
                            }
                        }

                        $forbiddenDependencyErrors[$fileModule->identifier->name][$usedSymbolModule->identifier->name][] = new ForbiddenDependencyError(
                            $filePathRelative,
                            $fileModule,
                            $usedSymbolPathRelative,
                            $usedSymbolModule,
                        );

                    } else {
                        $uncoveredDependencyErrors[$fileModule->identifier->name][] = new UncoveredDependencyError(
                            $filePathRelative,
                            $fileModule,
                            $usedSymbolPathRelative,
                        );
                    }
                }
            }
        }

        $unusedDependencyErrors = [];

        foreach ($allModules as $module) {
            if ($module->dependencies === null || $module->dependencies === []) {
                continue;
            }

            foreach ($module->dependencies as $declaredDep) {
                if (!isset($usedDependencies[$module->identifier->name][$declaredDep->name])) {
                    $unusedDependencyErrors[$module->identifier->name][] = new UnusedDependencyError(
                        $module,
                        $declaredDep,
                    );
                }
            }
        }

        return new Result($forbiddenDependencyErrors, $uncoveredDependencyErrors, $missingModuleErrors, $unusedDependencyErrors);
    }

    /**
     * @param list<Module> $allModules Already flattened list of all modules (including nested children)
     * @return array<string, non-empty-list<Module>>
     */
    private function buildFilesToModulesMap(
        string $baseDir,
        array $allModules,
    ): array
    {
        $modulesByName = [];

        foreach ($allModules as $module) {
            $modulesByName[$module->identifier->name] = $module;
        }

        $map = [];
        $cache = [];

        foreach ($allModules as $module) {
            foreach ($this->resolveModuleFiles($baseDir, $module, $modulesByName, $cache, []) as $filePath) {
                if (!in_array($module, $map[$filePath] ?? [], true)) {
                    $map[$filePath][] = $module;
                }
            }
        }

        return $map;
    }

    /**
     * Recursively collects all modules, including inline Module instances from the modules property.
     *
     * @param list<Module> $modules
     * @return list<Module>
     */
    private function collectAllModules(array $modules): array
    {
        $all = [];

        foreach ($modules as $module) {
            $all[] = $module;

            foreach ($this->collectAllModules($module->modules) as $nested) {
                $all[] = $nested;
            }
        }

        return $all;
    }

    /**
     * @param array<string, Module> $modulesByName
     * @param array<string, list<string>> $cache
     * @param list<string> $resolving Module names currently being resolved (for cycle detection)
     * @return list<string>
     */
    private function resolveModuleFiles(
        string $baseDir,
        Module $module,
        array $modulesByName,
        array &$cache,
        array $resolving,
    ): array
    {
        if (isset($cache[$module->identifier->name])) {
            return $cache[$module->identifier->name];
        }

        if (in_array($module->identifier->name, $resolving, true)) {
            throw new LogicException('Circular module reference detected: ' . implode(' -> ', [...$resolving, $module->identifier->name]));
        }

        $resolving[] = $module->identifier->name;
        $files = $this->getFilesInModule($baseDir, $module);

        foreach ($module->modules as $child) {
            foreach ($this->resolveModuleFiles($baseDir, $child, $modulesByName, $cache, $resolving) as $filePath) {
                $files[] = $filePath;
            }
        }

        $cache[$module->identifier->name] = $files;

        return $files;
    }

    /**
     * @return list<string>
     */
    private function getFilesInModule(
        string $baseDir,
        Module $module,
    ): array
    {
        $files = [];

        foreach ($module->paths as $path) {
            $absolutePath = $this->isAbsolutePath($path) ? $path : "{$baseDir}/{$path}";

            if (is_file($absolutePath)) {
                $files[] = $this->realPath($absolutePath);

            } elseif (is_dir($absolutePath)) {
                foreach ($this->findPhpFiles($absolutePath) as $file) {
                    $files[] = $this->realPath($file->getPathname());
                }

            } else {
                throw new LogicException("Path {$absolutePath} used by module {$module->identifier->name} does not exist");
            }
        }

        return $files;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function findPhpFiles(string $directory): iterable
    {
        // RecursiveDirectoryIterator does not follow symlinks by default (no FOLLOW_SYMLINKS flag),
        // which prevents infinite loops with circular symlinks
        $iterator = new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        $recursiveIterator = new RecursiveIteratorIterator($iterator);

        foreach ($recursiveIterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                yield $file;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getUsedClassLikeSymbols(
        Parser $phpParser,
        string $code,
    ): array
    {
        $nameResolver = new NameResolver();
        $usedSymbolsExtractor = new ExtractUsedClassLikeSymbolsVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($usedSymbolsExtractor);

        $ast = $phpParser->parse($code);

        if ($ast === null) {
            throw new LogicException('Failed to parse PHP code');
        }

        $traverser->traverse($ast);

        return $usedSymbolsExtractor->getUsedSymbols();
    }

    private function getClassLikePath(
        ClassLoader $classLoader,
        string $usedSymbol,
    ): ?string
    {
        $pathFromClassLoader = $classLoader->findFile($usedSymbol);

        if ($pathFromClassLoader !== false) {
            return $pathFromClassLoader;
        }

        if (class_exists($usedSymbol) || interface_exists($usedSymbol, false) || trait_exists($usedSymbol, false)) {
            $pathFromReflection = (new ReflectionClass($usedSymbol))->getFileName();

            if ($pathFromReflection !== false) {
                return $pathFromReflection;
            }
        }

        return null;
    }

    private function realPath(string $path): string
    {
        $absolutePath = realpath($path);

        if ($absolutePath === false) {
            throw new LogicException("Path {$path} does not exist");
        }

        return str_replace('\\', '/', $absolutePath);
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:/|\\\\|[a-zA-Z]:)~', $path) === 1;
    }

    private function shortenPath(
        string $path,
        string $basePath,
    ): string
    {
        $basePath = $this->realPath($basePath) . '/';

        if (str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath));

        } else {
            return $path;
        }
    }

    private function isBuiltInSymbol(string $usedSymbol): bool
    {
        return (class_exists($usedSymbol, false) || interface_exists($usedSymbol, false) || trait_exists($usedSymbol, false))
            && (new ReflectionClass($usedSymbol))->isInternal();
    }

    /**
     * @param array<string, non-empty-list<Module>> $filesToModulesMap
     * @param list<string> $fullyModulizedDirectories
     * @return array<string, list<MissingModuleError>>
     */
    private function detectMissingModules(
        string $baseDir,
        array $filesToModulesMap,
        array $fullyModulizedDirectories,
    ): array
    {
        $errors = [];

        foreach ($fullyModulizedDirectories as $directory) {
            foreach ($this->findPhpFiles("{$baseDir}/{$directory}") as $file) {
                $filePath = $this->realPath($file->getPathname());

                if (isset($filesToModulesMap[$filePath])) {
                    continue;
                }

                $errors[$directory][] = new MissingModuleError(
                    $directory,
                    $this->shortenPath($filePath, $baseDir),
                );
            }
        }

        return $errors;
    }

}
