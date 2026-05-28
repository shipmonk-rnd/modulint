<?php declare(strict_types = 1);

namespace ShipMonk\Modulint;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeVisitorAbstract;
use function array_keys;

final class ExtractUsedClassLikeSymbolsVisitor extends NodeVisitorAbstract
{

    /**
     * @var array<string, true>
     */
    private array $usedSymbols = [];

    /**
     * @return null
     */
    public function enterNode(Node $node)
    {
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
