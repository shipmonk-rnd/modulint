<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\ResultFormatter;

use ShipMonk\Modulint\Error\ForbiddenDependencyError;
use ShipMonk\Modulint\Error\UncoveredDependencyError;
use ShipMonk\Modulint\Exception\LogicException;
use ShipMonk\Modulint\Result;
use ShipMonk\Modulint\ResultFormatter;
use function array_column;
use function array_keys;
use function array_map;
use function array_values;
use function basename;
use function dirname;
use function fwrite;
use function max;
use function sprintf;
use function str_repeat;
use function str_replace;
use function strlen;
use function usort;

final class ConsoleResultFormatter implements ResultFormatter
{

    private const COLORS = [
        '<red>' => "\033[31m",
        '<green>' => "\033[32m",
        '<orange>' => "\033[33m",
        '<gray>' => "\033[90m",
        '</red>' => "\033[0m",
        '</green>' => "\033[0m",
        '</orange>' => "\033[0m",
        '</gray>' => "\033[0m",
        '<green-bg>' => "\033[30;42m",
        '</green-bg>' => "\033[0m",
    ];

    /**
     * @var resource
     */
    private $resource;

    /**
     * @param resource $resource
     */
    public function __construct(
        $resource,
        private readonly bool $noColor,
    )
    {
        $this->resource = $resource;
    }

    public function format(Result $result): void
    {
        foreach ($result->missingModuleErrors as $directory => $errors) {
            $this->print(sprintf("<red>[MISSING]</red>   Directory '<green>%s</green>' contain files that are not part of any module\n", $directory));

            foreach ($errors as $error) {
                $this->print(sprintf('%s%s', str_repeat(' ', 14), $this->formatPath($error->filePath)));
                $this->print("\n");
            }

            $this->print("\n");
        }

        foreach ($result->forbiddenDependencyErrors as $sourceModuleName => $forbiddenDependencyErrors1) {
            foreach ($forbiddenDependencyErrors1 as $dependencyModuleName => $forbiddenDependencyErrors2) {
                $this->print("<red>[FORBIDDEN]</red> Module '<green>{$sourceModuleName}</green>' has forbidden dependency on module '<green>{$dependencyModuleName}</green>'\n");
                $this->printErrors($forbiddenDependencyErrors2);
            }
        }

        foreach ($result->uncoveredDependencyErrors as $sourceModuleName => $uncoveredDependencyErrors) {
            $this->print("<orange>[UNCOVERED]</orange> Module '<green>{$sourceModuleName}</green>' has dependency on files that are not part of any module\n");
            $this->printErrors($uncoveredDependencyErrors);
        }

        foreach ($result->unusedDependencyErrors as $sourceModuleName => $errors) {
            $this->print("<orange>[UNUSED]</orange>    Module '<green>{$sourceModuleName}</green>' declares dependencies that are never used\n");

            $indent = str_repeat(' ', 14);

            foreach ($errors as $error) {
                $this->print(sprintf("%s%s\n", $indent, $error->unusedDependency->name));
            }

            $this->print("\n");
        }

        if ($result->isSuccessful()) {
            $this->print("\n");
            $this->print(sprintf("<green-bg> %-*s </green-bg>\n", 80, ''));
            $this->print(sprintf("<green-bg> %-*s </green-bg>\n", 80, '[OK] No module dependency issues found'));
            $this->print(sprintf("<green-bg> %-*s </green-bg>\n", 80, ''));
            $this->print("\n");
        }
    }

    /**
     * @param list<ForbiddenDependencyError|UncoveredDependencyError> $errors
     */
    private function printErrors(array $errors): void
    {
        usort($errors, static function (ForbiddenDependencyError|UncoveredDependencyError $a, ForbiddenDependencyError|UncoveredDependencyError $b): int {
            return [$a->sourcePath, $a->dependencyPath] <=> [$b->sourcePath, $b->dependencyPath];
        });

        $indent = str_repeat(' ', 14);
        $sourcePathMaxLength = max(0, ...array_map('strlen', array_column($errors, 'sourcePath'))) + strlen('<gray></gray>');

        foreach ($errors as $error) {
            $this->print(sprintf("%s%-*s -> %s\n", $indent, $sourcePathMaxLength + 0, $this->formatPath($error->sourcePath), $this->formatPath($error->dependencyPath)));
        }

        $this->print("\n");
    }

    private function print(string $string): void
    {
        $result = fwrite($this->resource, $this->colorize($string));

        if ($result === false) {
            throw new LogicException('Could not write to output stream.');
        }
    }

    private function formatPath(string $path): string
    {
        return sprintf('<gray>%s/</gray>%s', dirname($path), basename($path));
    }

    private function colorize(string $string): string
    {
        return str_replace(
            array_keys(self::COLORS),
            $this->noColor ? '' : array_values(self::COLORS),
            $string,
        );
    }

}
