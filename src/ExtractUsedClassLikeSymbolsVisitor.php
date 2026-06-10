<?php declare(strict_types = 1);

namespace ShipMonk\Modulint;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use function array_fill_keys;
use function array_keys;

final class ExtractUsedClassLikeSymbolsVisitor extends NodeVisitorAbstract
{

    /**
     * @var array<string, true>
     */
    private array $usedSymbols = [];

    /**
     * @var array<string, true>
     */
    private array $ignoredAttributes;

    /**
     * @param list<string> $ignoredAttributes
     */
    public function __construct(array $ignoredAttributes = [])
    {
        $this->ignoredAttributes = array_fill_keys($ignoredAttributes, true);
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Attribute && $node->name instanceof FullyQualified) {
            $this->usedSymbols[$node->name->toString()] = true;

            if (isset($this->ignoredAttributes[$node->name->toString()])) {
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

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
