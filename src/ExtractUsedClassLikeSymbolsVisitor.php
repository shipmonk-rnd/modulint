<?php declare(strict_types = 1);

namespace ShipMonk\Modulint;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeVisitorAbstract;
use function array_keys;

final class ExtractUsedClassLikeSymbolsVisitor extends NodeVisitorAbstract
{

    /**
     * @var array<string, true>
     */
    private array $usedSymbols = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Attribute && $node->name instanceof FullyQualified) {
            // recorded on attribute entry, so the attribute class counts as a dependency
            // even when a custom visitor skips the attribute's children
            $this->usedSymbols[$node->name->toString()] = true;

            return null;
        }

        if ($node instanceof FullyQualified) {
            $this->usedSymbols[$node->toString()] = true;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function getUsedSymbols(): array
    {
        return array_keys($this->usedSymbols);
    }

}
