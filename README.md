# Modulint - module dependency enforcer for PHP

- 🧱 **Module-aware:** define modules over your codebase and declare which other modules each one may depend on
- 🚦 **Strict:** detects forbidden, uncovered, missing and unused module dependencies
- 🪶 **Lightweight:** single static analysis pass over your sources
- ⚙️ **Configurable:** pure PHP config, modules identified by your own enum
- ✨ **Compatible:** PHP 8.1+

## Why?

Once a codebase grows, hand-written rules like "the `Logging` module must not depend on `Doctrine`" tend to drift. `shipmonk/modulint` lets you declare those rules as data and enforce them in CI.

A *module* is a named bag of paths (files and/or directories). For each module you declare which other modules it is allowed to depend on. Modulint walks every PHP file in each module, parses it, resolves used class-like symbols (classes, interfaces, traits, enums) via Composer's autoloader, looks up which module they belong to, and reports:

- **Forbidden dependency** — module `X` uses a class belonging to module `Y`, and `Y` is not in `X`'s allowed dependencies.
- **Uncovered dependency** — module `X` uses a class that is not part of any declared module.
- **Missing module** — a file inside a "fully-modulized" directory is not covered by any module.
- **Unused dependency** — module `X` declares a dependency on module `Y` but never actually uses anything from it.
- **Overlapping modules** (opt-in) — a file belongs to two modules that are not nested in each other.

## Installation

```sh
composer require --dev shipmonk/modulint
```

## Usage

Create `modulint.php` in your project root:

```php
<?php declare(strict_types = 1);

use ShipMonk\Modulint\Config\Configuration;
use ShipMonk\Modulint\Module;
use ShipMonk\Modulint\ModuleIdentifier;

enum AppModule: string implements ModuleIdentifier
{
    case Api = 'Api';
    case Doctrine = 'Doctrine';
    case Logging = 'Logging';
    case VendorDoctrine = 'VendorDoctrine';
    case VendorPsrLog = 'VendorPsrLog';
}

return (new Configuration())
    ->addModule(new Module(
        AppModule::Api,
        paths: ['src/Api'],
        dependencies: [AppModule::Doctrine, AppModule::Logging],
    ))
    ->addModule(new Module(
        AppModule::Doctrine,
        paths: ['src/Doctrine'],
        dependencies: [AppModule::VendorDoctrine],
    ))
    ->addModule(new Module(
        AppModule::Logging,
        paths: ['src/Logging'],
        dependencies: [AppModule::VendorPsrLog],
    ))

    // vendors with `dependencies: null` may be depended on by anyone and never report unused/forbidden
    ->addModule(new Module(AppModule::VendorDoctrine, ['vendor/doctrine'], dependencies: null))
    ->addModule(new Module(AppModule::VendorPsrLog, ['vendor/psr/log'], dependencies: null))

    // every file under src/ must belong to some module
    ->addFullyModulizedDirectory('src');
```

Then run:

```sh
vendor/bin/modulint
```

Exit code is `0` when everything is clean and `1` otherwise.

## Example output

When something is wrong, modulint groups the violations by type and points at the exact file-to-file edge that broke the rule:

```
[MISSING]   Directory 'src' contain files that are not part of any module
              src/Legacy/MailerHelper.php
              src/Legacy/LegacyClock.php

[FORBIDDEN] Module 'Logging' has forbidden dependency on module 'Doctrine'
              src/Logging/DatabaseLogHandler.php -> src/Doctrine/EntityManager.php

[UNCOVERED] Module 'Api' has dependency on files that are not part of any module
              src/Api/UserController.php -> src/Legacy/MailerHelper.php

[UNUSED]    Module 'Api' declares dependencies that are never used
              Logging
```

When everything checks out:

```
 [OK] No module dependency issues found
```

## Concepts

### Module identifier

`ModuleIdentifier` is an empty marker interface extending `UnitEnum`. You implement it by writing your own enum. Using an enum gives you autocompletion and refactoring support, and the IDE will tell you immediately if you reference a module that does not exist.

### Allowed dependencies

The `dependencies` constructor argument controls what a module can use:

- `[]` — module can only use symbols defined inside itself.
- `[OtherModule::X, OtherModule::Y]` — module can additionally use symbols from those modules.
- `null` — module can depend on *anything*. Use this for vendor-bag modules so that whoever depends on them never produces a forbidden-dependency error, and modulint will not report unused dependencies for them.

A dependency on a *parent* module implicitly allows dependencies on its nested `modules: [...]` children.

### Fully-modulized directories

`addFullyModulizedDirectory('src')` makes modulint complain about any `.php` file under `src/` that no module claims. This is how you keep the configuration honest as code is added.

### Vendor modules

A common pattern is to declare one module per relevant vendor package, pointing at `vendor/<package>` as its paths, with `dependencies: null`. That lets you assert which parts of your code may use which vendor library — typically the most useful kind of architectural rule.

### Excluded paths

`excludePaths` removes files or directories from a module's `paths`. Useful for carving a submodule out of a directory without nesting:

```php
new Module(
    AppModule::Shipping,
    paths: ['src/Shipping'],
    excludePaths: ['src/Shipping/Integration'], // owned by another module
    dependencies: [...],
)
```

### Allowed dependency paths

When a module may use *anything* under some path (typically `vendor/`) without enumerating vendor modules, allow the path instead:

```php
new Module(
    AppModule::Api,
    paths: ['src/Api'],
    dependencies: [AppModule::Logging],
    allowedDependencyPaths: ['vendor'], // anything under vendor/ is fine
)
```

To allow a path for *every* module, configure it globally:

```php
(new Configuration())->addAlwaysAllowedDependencyPath('vendor')
```

Symbols resolved into an allowed path never produce forbidden or uncovered errors, even when the path is also covered by other modules.

### Custom AST visitors

You can hook into the used-symbol extraction with your own php-parser visitors, e.g. to exclude class references inside arguments of a marker attribute from analysis:

```php
(new Configuration())->addAstVisitor(new class extends NodeVisitorAbstract {
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Attribute
            && $node->name instanceof FullyQualified
            && $node->name->toString() === InternalAllowedScope::class
        ) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
        return null;
    }
})
```

With that config, `#[InternalAllowedScope([SomeClass::class, 'method'])]` does not make the enclosing module depend on `SomeClass` (the attribute class itself still counts). Custom visitors run after name resolution (you always see fully qualified names) and before the extracting visitor; returning `NodeTraverser::DONT_TRAVERSE_CHILDREN` excludes a node's children from dependency analysis. Visitors are reused for all analyzed files, so keep them stateless.

### Flat modules

`(new Configuration())->enforceFlatModules()` reports any file claimed by two modules where neither is a (transitive) child of the other. Parent/child overlap from nested `modules: [...]` stays allowed.

### Baseline

To adopt modulint in a codebase with existing violations, generate a baseline:

```sh
vendor/bin/modulint --generate-baseline
```

This writes all current errors to `modulint-baseline.php` (customizable via `setBaselineFile()`). When that file exists, the listed errors are ignored on subsequent runs, and entries that no longer match any real error are reported as stale so the baseline only ever shrinks.

## Disable colored output

Set `NO_COLOR` to disable ANSI colors:

```sh
NO_COLOR=1 vendor/bin/modulint
```

## Contributing

- Check your code with `composer check`
- Autofix coding-style with `composer fix:cs`
- All functionality must be tested

## Supported PHP versions

- Runtime requires PHP 8.1+
