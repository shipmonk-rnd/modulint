<?php declare(strict_types = 1);

namespace ShipMonk\Modulint\Tests;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use ShipMonk\Modulint\Fixtures\TestModuleIdentifier;
use ShipMonk\Modulint\Module;
use ShipMonk\Modulint\Modulint;

final class ModulintTest extends TestCase
{

    public function testEnforceWithEmptyModulesArray(): void
    {
        $modulint = new Modulint();
        $classLoader = $this->createMock(ClassLoader::class);

        $result = $modulint->enforce($classLoader, __DIR__, []);

        self::assertTrue($result->isSuccessful());
        self::assertSame([], $result->forbiddenDependencyErrors);
        self::assertSame([], $result->uncoveredDependencyErrors);
        self::assertSame([], $result->missingModuleErrors);
        self::assertSame([], $result->unusedDependencyErrors);
    }

    public function testEnforceWithForbiddenDependency(): void
    {
        $modulint = new Modulint();
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

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $moduleB]);

        self::assertFalse($result->isSuccessful());
        self::assertNotSame([], $result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleA', $result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleB', $result->forbiddenDependencyErrors['ModuleA']);
    }

    public function testEnforceWithAllowedDependency(): void
    {
        $modulint = new Modulint();
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

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $moduleB]);

        self::assertTrue($result->isSuccessful());
        self::assertSame([], $result->forbiddenDependencyErrors);
        self::assertSame([], $result->uncoveredDependencyErrors);
        self::assertSame([], $result->missingModuleErrors);
        self::assertSame([], $result->unusedDependencyErrors);
    }

    public function testEnforceWithUncoveredDependency(): void
    {
        $modulint = new Modulint();
        $classLoader = $this->createClassLoaderMock();

        $moduleC = new Module(
            TestModuleIdentifier::ModuleC,
            [__DIR__ . '/_fixtures/ModuleC'],
            [],
            [],
        );

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleC]);

        self::assertFalse($result->isSuccessful());
        self::assertNotSame([], $result->uncoveredDependencyErrors);
        self::assertArrayHasKey('ModuleC', $result->uncoveredDependencyErrors);
    }

    public function testEnforceWithMissingModuleError(): void
    {
        $modulint = new Modulint();
        $classLoader = $this->createClassLoaderMock();

        $result = $modulint->enforce(
            $classLoader,
            __DIR__,
            [],
            ['_fixtures/FullyModulized'],
        );

        self::assertFalse($result->isSuccessful());
        self::assertNotSame([], $result->missingModuleErrors);
        self::assertArrayHasKey('_fixtures/FullyModulized', $result->missingModuleErrors);
    }

    public function testEnforceWithModuleWithNullDependencies(): void
    {
        $modulint = new Modulint();
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

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $moduleB]);

        self::assertTrue($result->isSuccessful());
        self::assertSame([], $result->forbiddenDependencyErrors);
        self::assertSame([], $result->uncoveredDependencyErrors);
        self::assertSame([], $result->missingModuleErrors);
        self::assertSame([], $result->unusedDependencyErrors);
    }

    public function testEnforceWithNestedModuleAllowedDependency(): void
    {
        $modulint = new Modulint();
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

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $moduleB, $parentAB, $moduleD]);

        self::assertTrue($result->isSuccessful());
        self::assertSame([], $result->forbiddenDependencyErrors);
        self::assertSame([], $result->uncoveredDependencyErrors);
        self::assertSame([], $result->unusedDependencyErrors);
    }

    public function testEnforceWithNestedModuleForbiddenDependency(): void
    {
        $modulint = new Modulint();
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

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $moduleB, $parentAB, $moduleD]);

        self::assertFalse($result->isSuccessful());
        self::assertNotSame([], $result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleD', $result->forbiddenDependencyErrors);
    }

    public function testEnforceWithNestedModuleDirectChildDependency(): void
    {
        $modulint = new Modulint();
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

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $moduleB, $parentAB, $moduleD]);

        self::assertTrue($result->isSuccessful());
    }

    public function testEnforceWithNestedModuleMissingModuleDetection(): void
    {
        $modulint = new Modulint();
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
        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $parentAB], ['_fixtures/ModuleA']);

        self::assertTrue($result->isSuccessful());
        self::assertSame([], $result->missingModuleErrors);
    }

    public function testEnforceWithUnusedDependency(): void
    {
        $modulint = new Modulint();
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

        $result = $modulint->enforce($classLoader, __DIR__, [$moduleA, $moduleB, $moduleC]);

        self::assertFalse($result->isSuccessful());
        self::assertSame([], $result->forbiddenDependencyErrors);
        self::assertArrayHasKey('ModuleA', $result->unusedDependencyErrors);
        self::assertCount(1, $result->unusedDependencyErrors['ModuleA']);
        self::assertSame(TestModuleIdentifier::ModuleC, $result->unusedDependencyErrors['ModuleA'][0]->unusedDependency);
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
                    'ShipMonk\\Modulint\\Fixtures\\External\\ExternalClass' => __DIR__ . '/_fixtures/External/ExternalClass.php',
                ];

                return $map[$className] ?? false;
            });

        return $classLoader;
    }

}
