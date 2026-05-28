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
