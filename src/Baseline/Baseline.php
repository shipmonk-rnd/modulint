<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Baseline;

use ShipMonk\Modulint\Exception\LogicException;
use ShipMonk\Modulint\Result;
use function in_array;
use function is_array;
use function is_file;
use function is_string;

final class Baseline
{

    /**
     * @param array<string, array<string, list<string>>> $forbiddenDependencies indexed by [sourceModule][targetModule], values are "sourcePath -> dependencyPath"
     * @param array<string, list<string>> $uncoveredDependencies indexed by [sourceModule], values are "sourcePath -> dependencyPath"
     * @param array<string, list<string>> $unusedDependencies indexed by [sourceModule], values are dependency module names
     * @param array<string, list<string>> $missingModules indexed by [directory], values are file paths
     */
    public function __construct(
        public readonly array $forbiddenDependencies = [],
        public readonly array $uncoveredDependencies = [],
        public readonly array $unusedDependencies = [],
        public readonly array $missingModules = [],
    )
    {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new LogicException("Baseline file {$path} does not exist");
        }

        $data = require $path;

        if (!is_array($data)) {
            throw new LogicException("Baseline file {$path} must return an array");
        }

        $forbiddenDependencies = [];

        foreach (self::extractStringKeyedArray($data, 'forbiddenDependencies', $path) as $sourceModuleName => $errorsByTargetModule) {
            if (!is_array($errorsByTargetModule)) {
                throw new LogicException("Invalid 'forbiddenDependencies' section in baseline file {$path}");
            }

            foreach (self::extractStringListMap($errorsByTargetModule, $path) as $targetModuleName => $fingerprints) {
                $forbiddenDependencies[$sourceModuleName][$targetModuleName] = $fingerprints;
            }
        }

        return new self(
            $forbiddenDependencies,
            self::extractStringListMap(self::extractStringKeyedArray($data, 'uncoveredDependencies', $path), $path),
            self::extractStringListMap(self::extractStringKeyedArray($data, 'unusedDependencies', $path), $path),
            self::extractStringListMap(self::extractStringKeyedArray($data, 'missingModules', $path), $path),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractStringKeyedArray(
        array $data,
        string $key,
        string $path,
    ): array
    {
        $section = $data[$key] ?? [];

        if (!is_array($section)) {
            throw new LogicException("Invalid '{$key}' section in baseline file {$path}");
        }

        $result = [];

        foreach ($section as $sectionKey => $value) {
            if (!is_string($sectionKey)) {
                throw new LogicException("Invalid '{$key}' section in baseline file {$path}");
            }

            $result[$sectionKey] = $value;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, list<string>>
     */
    private static function extractStringListMap(
        array $data,
        string $path,
    ): array
    {
        $result = [];

        foreach ($data as $key => $values) {
            if (!is_string($key) || !is_array($values)) {
                throw new LogicException("Invalid section in baseline file {$path}");
            }

            $result[$key] = [];

            foreach ($values as $value) {
                if (!is_string($value)) {
                    throw new LogicException("Invalid section in baseline file {$path}");
                }

                $result[$key][] = $value;
            }
        }

        return $result;
    }

    /**
     * Removes errors matched by this baseline from the result and reports baseline entries
     * that no longer match any error as stale.
     */
    public function apply(Result $result): Result
    {
        $matchedFingerprints = [];

        $forbiddenDependencyErrors = [];

        foreach ($result->forbiddenDependencyErrors as $sourceModuleName => $errorsByTargetModule) {
            foreach ($errorsByTargetModule as $targetModuleName => $errors) {
                $baselinedFingerprints = $this->forbiddenDependencies[$sourceModuleName][$targetModuleName] ?? [];

                foreach ($errors as $error) {
                    $fingerprint = "{$error->sourcePath} -> {$error->dependencyPath}";

                    if (in_array($fingerprint, $baselinedFingerprints, true)) {
                        $matchedFingerprints['forbiddenDependencies'][$sourceModuleName][$targetModuleName][$fingerprint] = true;
                        continue;
                    }

                    $forbiddenDependencyErrors[$sourceModuleName][$targetModuleName][] = $error;
                }
            }
        }

        $uncoveredDependencyErrors = [];

        foreach ($result->uncoveredDependencyErrors as $sourceModuleName => $errors) {
            $baselinedFingerprints = $this->uncoveredDependencies[$sourceModuleName] ?? [];

            foreach ($errors as $error) {
                $fingerprint = "{$error->sourcePath} -> {$error->dependencyPath}";

                if (in_array($fingerprint, $baselinedFingerprints, true)) {
                    $matchedFingerprints['uncoveredDependencies'][$sourceModuleName][$fingerprint] = true;
                    continue;
                }

                $uncoveredDependencyErrors[$sourceModuleName][] = $error;
            }
        }

        $unusedDependencyErrors = [];

        foreach ($result->unusedDependencyErrors as $sourceModuleName => $errors) {
            $baselinedFingerprints = $this->unusedDependencies[$sourceModuleName] ?? [];

            foreach ($errors as $error) {
                $fingerprint = $error->unusedDependency->name;

                if (in_array($fingerprint, $baselinedFingerprints, true)) {
                    $matchedFingerprints['unusedDependencies'][$sourceModuleName][$fingerprint] = true;
                    continue;
                }

                $unusedDependencyErrors[$sourceModuleName][] = $error;
            }
        }

        $missingModuleErrors = [];

        foreach ($result->missingModuleErrors as $directory => $errors) {
            $baselinedFingerprints = $this->missingModules[$directory] ?? [];

            foreach ($errors as $error) {
                $fingerprint = $error->filePath;

                if (in_array($fingerprint, $baselinedFingerprints, true)) {
                    $matchedFingerprints['missingModules'][$directory][$fingerprint] = true;
                    continue;
                }

                $missingModuleErrors[$directory][] = $error;
            }
        }

        $staleBaselineErrors = [];

        foreach ($this->forbiddenDependencies as $sourceModuleName => $fingerprintsByTargetModule) {
            foreach ($fingerprintsByTargetModule as $targetModuleName => $fingerprints) {
                foreach ($fingerprints as $fingerprint) {
                    if (!isset($matchedFingerprints['forbiddenDependencies'][$sourceModuleName][$targetModuleName][$fingerprint])) {
                        $staleBaselineErrors[] = "forbidden dependency of '{$sourceModuleName}' on '{$targetModuleName}': {$fingerprint}";
                    }
                }
            }
        }

        foreach ($this->uncoveredDependencies as $sourceModuleName => $fingerprints) {
            foreach ($fingerprints as $fingerprint) {
                if (!isset($matchedFingerprints['uncoveredDependencies'][$sourceModuleName][$fingerprint])) {
                    $staleBaselineErrors[] = "uncovered dependency of '{$sourceModuleName}': {$fingerprint}";
                }
            }
        }

        foreach ($this->unusedDependencies as $sourceModuleName => $fingerprints) {
            foreach ($fingerprints as $fingerprint) {
                if (!isset($matchedFingerprints['unusedDependencies'][$sourceModuleName][$fingerprint])) {
                    $staleBaselineErrors[] = "unused dependency of '{$sourceModuleName}' on '{$fingerprint}'";
                }
            }
        }

        foreach ($this->missingModules as $directory => $fingerprints) {
            foreach ($fingerprints as $fingerprint) {
                if (!isset($matchedFingerprints['missingModules'][$directory][$fingerprint])) {
                    $staleBaselineErrors[] = "missing module for file in '{$directory}': {$fingerprint}";
                }
            }
        }

        return new Result(
            $forbiddenDependencyErrors,
            $uncoveredDependencyErrors,
            $missingModuleErrors,
            $unusedDependencyErrors,
            $result->overlappingModuleErrors,
            $staleBaselineErrors,
        );
    }

}
