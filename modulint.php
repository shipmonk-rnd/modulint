<?php declare(strict_types = 1);

use ShipMonk\Modulint\Config\Configuration;
use ShipMonk\Modulint\Module;
use ShipMonk\Modulint\ModuleIdentifier;

enum ModulintModule: string implements ModuleIdentifier
{

    case Config = 'Config';
    case Core = 'Core';
    case Exception = 'Exception';
    case VendorComposerClassLoader = 'VendorComposerClassLoader';
    case VendorNikicPhpParser = 'VendorNikicPhpParser';

}

return (new Configuration())
    ->addModule(new Module(
        ModulintModule::Config,
        paths: ['src/Config'],
        dependencies: [ModulintModule::Core],
    ))
    ->addModule(new Module(
        ModulintModule::Core,
        paths: [
            'src/Modulint.php',
            'src/Module.php',
            'src/ModuleIdentifier.php',
            'src/Result.php',
            'src/ResultFormatter.php',
            'src/ExtractUsedClassLikeSymbolsVisitor.php',
            'src/Baseline',
            'src/Error',
            'src/ResultFormatter',
        ],
        dependencies: [
            ModulintModule::Config,
            ModulintModule::Exception,
            ModulintModule::VendorComposerClassLoader,
            ModulintModule::VendorNikicPhpParser,
        ],
    ))
    ->addModule(new Module(ModulintModule::Exception, ['src/Exception'], dependencies: []))
    ->addModule(new Module(ModulintModule::VendorComposerClassLoader, ['vendor/composer/ClassLoader.php']))
    ->addModule(new Module(ModulintModule::VendorNikicPhpParser, ['vendor/nikic/php-parser']))
    ->addFullyModulizedDirectory('src');
