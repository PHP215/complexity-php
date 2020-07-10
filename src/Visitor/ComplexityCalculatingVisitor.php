<?php declare(strict_types=1);
/*
 * This file is part of sebastian/complexity.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\Complexity;

use function assert;
use function is_array;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class ComplexityCalculatingVisitor extends NodeVisitorAbstract
{
    /**
     * @psalm-var list<Complexity>
     */
    private $result = [];

    public function enterNode(Node $node): void
    {
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return;
        }

        if ($node instanceof ClassMethod) {
            $name = $this->classMethodName($node);
        } else {
            $name = $this->functionName($node);
        }

        $statements = $node->getStmts();

        assert(is_array($statements));

        $this->result[] = new Complexity(
            $name,
            $this->cyclomaticComplexity($statements)
        );
    }

    public function result(): ComplexityCollection
    {
        return ComplexityCollection::fromList(...$this->result);
    }

    /**
     * @param Stmt[] $statements
     */
    private function cyclomaticComplexity(array $statements): int
    {
        $traverser = new NodeTraverser;

        $cyclomaticComplexityCalculatingVisitor = new CyclomaticComplexityCalculatingVisitor;

        $traverser->addVisitor($cyclomaticComplexityCalculatingVisitor);

        /* @noinspection UnusedFunctionResultInspection */
        $traverser->traverse($statements);

        return $cyclomaticComplexityCalculatingVisitor->cyclomaticComplexity();
    }

    private function classMethodName(ClassMethod $node): string
    {
        $class = $node->getAttribute('parent');

        assert($class instanceof Class_);
        assert(isset($class->namespacedName));
        assert($class->namespacedName instanceof Name);

        return $class->namespacedName->toString() . '::' . $node->name->toString();
    }

    private function functionName(Function_ $node): string
    {
        assert(isset($node->namespacedName));
        assert($node->namespacedName instanceof Name);

        return $node->namespacedName->toString();
    }
}
