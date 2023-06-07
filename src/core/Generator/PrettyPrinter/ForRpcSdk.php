<?php

namespace SPF\Generator\PrettyPrinter;

use PhpParser\PrettyPrinter\Standard;
use PhpParser\Node\Stmt;
use PhpParser\Node\Name;
use SPF\Exception\InvalidArgumentException;
use SPF\Generator\RpcSdk;
use SPF\Struct\BaseStruct;

class ForRpcSdk extends Standard
{
    /**
     * The special stmts.
     *
     * @var array ['name' => Stmt]
     */
    protected $specialStmts = [];

    /**
     * Prepend namespace prefix.
     *
     * @var bool
     */
    public $prependNamespacePrefix = true;

    /**
     * Pretty prints an array of nodes (statements) and indents them optionally.
     *
     * @param Node[] $nodes  Array of nodes
     * @param bool   $indent Whether to indent the printed nodes
     *
     * @return string Pretty printed statements
     */
    protected function pStmts(array $nodes, bool $indent = true) : string
    {
        if ($indent) {
            $this->indent();
        }

        $result = '';
        foreach ($nodes as $node) {
            if (!$this->canPrint($node)) {
                continue;
            }
 
            // filter and transfer the node struct
            $this->filterPrint($node);

            if ($this->ifPrependLn($node)) {
                $result .= $this->nl;
            }

            $comments = $node->getComments();
            if ($comments) {
                $result .= $this->nl . $this->pComments($comments);
                if ($node instanceof Stmt\Nop) {
                    continue;
                }
            }

            $result .= $this->nl . $this->p($node);
        }

        if ($indent) {
            $this->outdent();
        }

        return $result;
    }

    /**
     * The node weather can be printed.
     *
     * @param Node $node
     *
     * @return boolean
     */
    protected function canPrint($node)
    {
        // any special node can print
        if ($this->isSpecialNode($node)) {
            return true;
        }

        // if the node has isPublic method and it`s property isn`t public cannot print
        if (method_exists($node, 'isPublic') && !$node->isPublic()) {
            return false;
        }

        // TODO maybe there is any other condition but not found
        
        return true;
    }

    /**
     * filter and transfer the node struct
     *
     * @param Node $node
     */
    protected function filterPrint($node)
    {
        // replace method content into the special stmts
        if ($node instanceof Stmt\ClassMethod) {
            if ($this->isSpecialNode($node)) {
                return ;
            } elseif (!$node->isMagic() && !$node->isAbstract() && !$node->isStatic()) {
                $node->stmts = $this->getSpecailStmt('replaceMethod');
            } elseif ($node->isStatic()) {
                $node->stmts = $this->getSpecailStmt('replaceStaticMethod');
            } else {
                $node->stmts = [];
            }
        }

        // append a call rpc method to the class
        if ($node instanceof Stmt\Class_) {
            foreach ($this->getSpecailStmt('appendCallRpc') as $stmt) {
                $node->stmts[] = $stmt;
            }
        }

        // replace function content into empty
        if ($node instanceof Stmt\Function_) {
            $node->stmts = [];
        }

        // append use namespace
        if ($node instanceof Stmt\Namespace_) {
            // append namespace prefix
            $namespace = (string) $node->name;
            $this->ifPrependNamespacePrefix($node, $namespace);

            if ($this->prependNamespacePrefix && strpos($namespace, RpcSdk::$namespacePrefix) === 0 && RpcSdk::$newNamespacePrefix) {
                $namespace = RpcSdk::$newNamespacePrefix . $namespace;
                $node->name = new Name($namespace);
            }
            
            $this->removeNamespaceUseIfMatched($node);

            $namespaceUsing = $this->getSpecailStmt('appendNamespaceUsing');
            while ($stmt = array_pop($namespaceUsing)) {
                array_unshift($node->stmts, $stmt);
            }
        }

        // append namespace use prefix
        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $namespaceUse) {
                $use = (string) $namespaceUse->name;
                if (strpos($use, RpcSdk::$namespacePrefix) === 0 && RpcSdk::$newNamespacePrefix && !is_subclass_of($use, BaseStruct::class)) {
                    $use = RpcSdk::$newNamespacePrefix . $use;
                    $namespaceUse->name = new Name($use);
                }
            }
        }
    }

    /**
     * Remove namespace uses matched provided rules.
     *
     * @param Stmt\Namespace_ $node
     */
    protected function removeNamespaceUseIfMatched(Stmt\Namespace_ $node)
    {
        foreach ($node->stmts as $pIndex => $stmt) {
            if ($stmt instanceof Stmt\Use_) {
                foreach ($stmt->uses as $index => $namespaceUse) {
                    $use = (string) $namespaceUse->name;
                    foreach (RpcSdk::$removeNamespaces as $pattern) {
                        $pregPattern = '/' . str_replace(['\\', '/'], ['\\\\', '\/'], $pattern) . '/';
                        if (preg_match($pregPattern, $use)) {
                            unset($stmt->uses[$index]);
                            continue;
                        }
                    }
                }
                // remove the whole child stmt, otherwise there will left `use ;` expression
                if (empty($stmt->uses)) {
                    unset($node->stmts[$pIndex]);
                }
            }
        }
    }

    /**
     * Whether if prepend namespace prefix
     *
     * @param Stmt $node
     * @param string $namespace
     */
    protected function ifPrependNamespacePrefix($node, $namespace = '')
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\Class_) {
                $fullClassName = $namespace . ($namespace ? '\\' : '') . $stmt->name;
                if (is_subclass_of($fullClassName, BaseStruct::class)) {
                    $this->prependNamespacePrefix = false;
                }
            }
        }
    }

    /**
     * Whether if prepend line break
     *
     * @param Node $node
     *
     * @return boolean
     */
    protected function ifPrependLn($node)
    {
        return ($node instanceof Stmt\Namespace_) || ($node instanceof Stmt\Function_) || ($node instanceof Stmt\ClassMethod)
             || ($node instanceof Stmt\Class_) || ($node instanceof Stmt\Trait_) || ($node instanceof Stmt\Interface_)
             || ($node instanceof Stmt\TraitUse);
    }

    /**
     * The node weather is special.
     *
     * @param Node $node
     *
     * @return boolean
     */
    protected function isSpecialNode($node)
    {
        return $node->getAttribute('special', false) === true;
    }

    /**
     * Set special stmts.
     *
     * @param string $name stmt`s name
     * @param Stmt|Stmt[] $stmt stmt`s experission
     *
     * @return $this
     */
    public function setSpecialStmt($name, $stmt)
    {
        $this->specialStmts[$name] = $stmt;

        return $this;
    }

    /**
     * Get the special stmt named $name
     *
     * @param string $name
     *
     * @return Stmt|Stmt[]
     */
    public function getSpecailStmt($name)
    {
        if (!isset($this->specialStmts[$name])) {
            throw new InvalidArgumentException("No special stmt [$name]");
        }

        return $this->specialStmts[$name];
    }
}
