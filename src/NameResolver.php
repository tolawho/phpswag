<?php

namespace PhpSwag;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

class NameResolver extends NodeVisitorAbstract
{
    private string $currentNamespace = '';
    private array $useAliases = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : '';
            $this->useAliases = [];
        } elseif ($node instanceof Use_) {
            foreach ($node->uses as $use) {
                $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                $this->useAliases[$alias] = $use->name->toString();
            }
        }
    }

    public function resolve(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name);
        $firstPart = $parts[0];

        if (isset($this->useAliases[$firstPart])) {
            $parts[0] = $this->useAliases[$firstPart];
            return implode('\\', $parts);
        }

        if ($this->currentNamespace === '') {
            return $name;
        }

        return $this->currentNamespace . '\\' . $name;
    }

    public function getCurrentNamespace(): string
    {
        return $this->currentNamespace;
    }

    public function getUseAliases(): array
    {
        return $this->useAliases;
    }
}
