<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Tests;

use Composer\Autoload\ClassLoader;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPUnit\Framework\TestCase;
use ShipMonk\Modulint\Baseline\Baseline;
use ShipMonk\Modulint\Baseline\BaselineGenerator;
use ShipMonk\Modulint\Config\Configuration;
use ShipMonk\Modulint\Fixtures\ModuleF\IgnoredAttr;
use ShipMonk\Modulint\Fixtures\TestModuleIdentifier;
use ShipMonk\Modulint\Module;
use ShipMonk\Modulint\Modulint;
use ShipMonk\Modulint\Result;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ModulintTest extends TestCase
{

    public function testEnforceWithEmptyModulesArray(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $result = $this->enforce($classLoader, new Configuration());

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->forbiddenDependencyErrors);
        self::assertEmpty($result->uncoveredDependencyErrors);
        self::assertEmpty($result->missingModuleErrors);
        self::assertEmpty($result->unusedDependencyErrors);
    }

    public function testEnforceWithForbiddenDependency(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB]));

        self::assertFalse($result->isSuccessful());
        self::assertNotEmpty($result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleA', $result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleB', $result->forbiddenDependencyErrors['ModuleA']);
    }

    public function testEnforceWithAllowedDependency(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [TestModuleIdentifier::ModuleB],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB]));

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->forbiddenDependencyErrors);
        self::assertEmpty($result->uncoveredDependencyErrors);
        self::assertEmpty($result->missingModuleErrors);
        self::assertEmpty($result->unusedDependencyErrors);
    }

    public function testEnforceWithUncoveredDependency(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleC = new Module(
            TestModuleIdentifier::ModuleC,
            [__DIR__ . '/_fixtures/ModuleC'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModule($moduleC));

        self::assertFalse($result->isSuccessful());
        self::assertNotEmpty($result->uncoveredDependencyErrors);
        self::assertArrayHasKey('ModuleC', $result->uncoveredDependencyErrors);
    }

    public function testEnforceWithMissingModuleError(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $result = $this->enforce(
            $classLoader,
            (new Configuration())->addFullyModulizedDirectory('_fixtures/FullyModulized'),
        );

        self::assertFalse($result->isSuccessful());
        self::assertNotEmpty($result->missingModuleErrors);
        self::assertArrayHasKey('_fixtures/FullyModulized', $result->missingModuleErrors);
    }

    public function testEnforceWithModuleWithNullDependencies(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            null,
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB]));

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->forbiddenDependencyErrors);
        self::assertEmpty($result->uncoveredDependencyErrors);
        self::assertEmpty($result->missingModuleErrors);
        self::assertEmpty($result->unusedDependencyErrors);
    }

    public function testEnforceWithNestedModuleAllowedDependency(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [TestModuleIdentifier::ModuleB],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );
        $parentAB = new Module(
            TestModuleIdentifier::ParentAB,
            [],
            [$moduleA, $moduleB],
        );
        $moduleD = new Module(
            TestModuleIdentifier::ModuleD,
            [__DIR__ . '/_fixtures/ModuleD'],
            [],
            [TestModuleIdentifier::ParentAB],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB, $parentAB, $moduleD]));

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->forbiddenDependencyErrors);
        self::assertEmpty($result->uncoveredDependencyErrors);
        self::assertEmpty($result->unusedDependencyErrors);
    }

    public function testEnforceWithNestedModuleForbiddenDependency(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );
        $parentAB = new Module(
            TestModuleIdentifier::ParentAB,
            [],
            [$moduleA, $moduleB],
        );
        $moduleD = new Module(
            TestModuleIdentifier::ModuleD,
            [__DIR__ . '/_fixtures/ModuleD'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB, $parentAB, $moduleD]));

        self::assertFalse($result->isSuccessful());
        self::assertNotEmpty($result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleD', $result->forbiddenDependencyErrors);
    }

    public function testEnforceWithNestedModuleDirectChildDependency(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [TestModuleIdentifier::ModuleB],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );
        $parentAB = new Module(
            TestModuleIdentifier::ParentAB,
            [],
            [$moduleA, $moduleB],
        );
        $moduleD = new Module(
            TestModuleIdentifier::ModuleD,
            [__DIR__ . '/_fixtures/ModuleD'],
            [],
            [TestModuleIdentifier::ModuleA], // depends on child directly, not parent
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB, $parentAB, $moduleD]));

        self::assertTrue($result->isSuccessful());
    }

    public function testEnforceWithNestedModuleMissingModuleDetection(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            null,
        );
        $parentAB = new Module(
            TestModuleIdentifier::ParentAB,
            [],
            [$moduleA],
            null,
        );

        // Files in ModuleA are covered by both ModuleA and ParentAB
        $result = $this->enforce(
            $classLoader,
            (new Configuration())
                ->addModules([$moduleA, $parentAB])
                ->addFullyModulizedDirectory('_fixtures/ModuleA'),
        );

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->missingModuleErrors);
    }

    public function testEnforceWithUnusedDependency(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [TestModuleIdentifier::ModuleB, TestModuleIdentifier::ModuleC],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );
        $moduleC = new Module(
            TestModuleIdentifier::ModuleC,
            [__DIR__ . '/_fixtures/ModuleC'],
            [],
            null,
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB, $moduleC]));

        self::assertFalse($result->isSuccessful());
        self::assertEmpty($result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleA', $result->unusedDependencyErrors);
        self::assertCount(1, $result->unusedDependencyErrors['ModuleA']);
        self::assertSame(TestModuleIdentifier::ModuleC, $result->unusedDependencyErrors['ModuleA'][0]->unusedDependency);
    }

    public function testEnforceWithExcludedPath(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleE = new Module(
            TestModuleIdentifier::ModuleE,
            [__DIR__ . '/_fixtures/ModuleE'],
            excludePaths: [__DIR__ . '/_fixtures/ModuleE/Sub'],
        );

        $result = $this->enforce(
            $classLoader,
            (new Configuration())
                ->addModule($moduleE)
                ->addFullyModulizedDirectory('_fixtures/ModuleE'),
        );

        self::assertFalse($result->isSuccessful());
        self::assertArrayHasKey('_fixtures/ModuleE', $result->missingModuleErrors);
        self::assertCount(1, $result->missingModuleErrors['_fixtures/ModuleE']);
        self::assertSame('_fixtures/ModuleE/Sub/ClassESub.php', $result->missingModuleErrors['_fixtures/ModuleE'][0]->filePath);
    }

    public function testEnforceWithAllowedDependencyPathForUncoveredSymbol(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleC = new Module(
            TestModuleIdentifier::ModuleC,
            [__DIR__ . '/_fixtures/ModuleC'],
            [],
            [],
            allowedDependencyPaths: [__DIR__ . '/_fixtures/External'],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModule($moduleC));

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->uncoveredDependencyErrors);
    }

    public function testEnforceWithGlobalAllowedDependencyPath(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleC = new Module(
            TestModuleIdentifier::ModuleC,
            [__DIR__ . '/_fixtures/ModuleC'],
            [],
            [],
        );

        $result = $this->enforce(
            $classLoader,
            (new Configuration())
                ->addModule($moduleC)
                ->addAlwaysAllowedDependencyPath('_fixtures/External'),
        );

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->uncoveredDependencyErrors);
    }

    public function testEnforceWithAllowedDependencyPathForCoveredSymbol(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = (new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [],
        ))->canDependOnPath(__DIR__ . '/_fixtures/ModuleB');
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB]));

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->forbiddenDependencyErrors);
    }

    public function testEnforceWithAttributeUsageNotIgnored(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleF = new Module(
            TestModuleIdentifier::ModuleF,
            [__DIR__ . '/_fixtures/ModuleF'],
            [],
            [],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleF, $moduleB]));

        self::assertFalse($result->isSuccessful());
        self::assertArrayHasKey('ModuleF', $result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleB', $result->forbiddenDependencyErrors['ModuleF']);
    }

    public function testEnforceWithAstVisitorIgnoringAttributeUsage(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleF = new Module(
            TestModuleIdentifier::ModuleF,
            [__DIR__ . '/_fixtures/ModuleF'],
            [],
            [],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );

        $ignoreAttributeArgumentsVisitor = new class extends NodeVisitorAbstract {

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Attribute
                    && $node->name instanceof FullyQualified
                    && $node->name->toString() === IgnoredAttr::class
                ) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                return null;
            }

        };

        $result = $this->enforce(
            $classLoader,
            (new Configuration())
                ->addModules([$moduleF, $moduleB])
                ->addAstVisitor($ignoreAttributeArgumentsVisitor),
        );

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->forbiddenDependencyErrors);
    }

    public function testEnforceWithOverlappingModules(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            null,
        );
        $moduleX = new Module(
            TestModuleIdentifier::ModuleX,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            null,
        );

        $result = $this->enforce(
            $classLoader,
            (new Configuration())
                ->addModules([$moduleA, $moduleX])
                ->enforceFlatModules(),
        );

        self::assertFalse($result->isSuccessful());
        self::assertArrayHasKey('ModuleA & ModuleX', $result->overlappingModuleErrors);
        self::assertSame('_fixtures/ModuleA/ClassA.php', $result->overlappingModuleErrors['ModuleA & ModuleX'][0]->filePath);
    }

    public function testEnforceWithNestedModulesNotOverlapping(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            null,
        );
        $parentAB = new Module(
            TestModuleIdentifier::ParentAB,
            [],
            [$moduleA],
            null,
        );

        $result = $this->enforce(
            $classLoader,
            (new Configuration())
                ->addModules([$moduleA, $parentAB])
                ->enforceFlatModules(),
        );

        self::assertTrue($result->isSuccessful());
        self::assertEmpty($result->overlappingModuleErrors);
    }

    public function testBaselineGenerateAndApply(): void
    {
        $classLoader = $this->createClassLoaderMock();

        $moduleA = new Module(
            TestModuleIdentifier::ModuleA,
            [__DIR__ . '/_fixtures/ModuleA'],
            [],
            [],
        );
        $moduleB = new Module(
            TestModuleIdentifier::ModuleB,
            [__DIR__ . '/_fixtures/ModuleB'],
            [],
            [],
        );
        $moduleC = new Module(
            TestModuleIdentifier::ModuleC,
            [__DIR__ . '/_fixtures/ModuleC'],
            [],
            [],
        );

        $result = $this->enforce($classLoader, (new Configuration())->addModules([$moduleA, $moduleB, $moduleC]));

        self::assertFalse($result->isSuccessful());
        self::assertNotEmpty($result->forbiddenDependencyErrors);
        self::assertNotEmpty($result->uncoveredDependencyErrors);

        $baselineFilePath = tempnam(sys_get_temp_dir(), 'modulint-baseline-');
        self::assertNotFalse($baselineFilePath);

        file_put_contents($baselineFilePath, (new BaselineGenerator())->generateFileContent($result));

        $baseline = Baseline::fromFile($baselineFilePath);
        unlink($baselineFilePath);

        $filteredResult = $baseline->apply($result);

        self::assertTrue($filteredResult->isSuccessful());
        self::assertEmpty($filteredResult->forbiddenDependencyErrors);
        self::assertEmpty($filteredResult->uncoveredDependencyErrors);
        self::assertEmpty($filteredResult->staleBaselineErrors);
    }

    public function testBaselineStaleEntry(): void
    {
        $baseline = new Baseline(
            forbiddenDependencies: ['ModuleA' => ['ModuleB' => ['nonexistent.php -> other.php']]],
        );

        $cleanResult = new Result([], [], [], []);
        $filteredResult = $baseline->apply($cleanResult);

        self::assertFalse($filteredResult->isSuccessful());
        self::assertCount(1, $filteredResult->staleBaselineErrors);
        self::assertStringContainsString('nonexistent.php -> other.php', $filteredResult->staleBaselineErrors[0]);
    }

    private function enforce(
        ClassLoader $classLoader,
        Configuration $configuration,
    ): Result
    {
        return (new Modulint())->enforce($classLoader, __DIR__, $configuration);
    }

    private function createClassLoaderMock(): ClassLoader
    {
        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->method('findFile')
            ->willReturnCallback(static function (string $className): string|false {
                $map = [
                    'ShipMonk\\Modulint\\Fixtures\\ModuleA\\ClassA' => __DIR__ . '/_fixtures/ModuleA/ClassA.php',
                    'ShipMonk\\Modulint\\Fixtures\\ModuleB\\ClassB' => __DIR__ . '/_fixtures/ModuleB/ClassB.php',
                    'ShipMonk\\Modulint\\Fixtures\\ModuleC\\ClassC' => __DIR__ . '/_fixtures/ModuleC/ClassC.php',
                    'ShipMonk\\Modulint\\Fixtures\\ModuleD\\ClassD' => __DIR__ . '/_fixtures/ModuleD/ClassD.php',
                    'ShipMonk\\Modulint\\Fixtures\\ModuleF\\IgnoredAttr' => __DIR__ . '/_fixtures/ModuleF/IgnoredAttr.php',
                    'ShipMonk\\Modulint\\Fixtures\\ModuleF\\AttributeUser' => __DIR__ . '/_fixtures/ModuleF/AttributeUser.php',
                    'ShipMonk\\Modulint\\Fixtures\\External\\ExternalClass' => __DIR__ . '/_fixtures/External/ExternalClass.php',
                ];

                return $map[$className] ?? false;
            });

        return $classLoader;
    }

}
