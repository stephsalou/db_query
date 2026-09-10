<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Table des symboles analysables, tous fichiers confondus. Prerequis de
 * l'interprocedural : sans vue globale, un appel vers un symbole d'un autre
 * fichier serait « non resolu » a tort.
 */
final class SymbolTable
{
    /** @var array<string,array{stmts:list<Node>,params:list<Node\Param>,file:string}> */
    private array $symbols = [];

    /** @param list<Node> $ast */
    public function addFile(array $ast, string $canonicalFile): void
    {
        $this->collect($ast, $canonicalFile, '');
    }

    /** @param list<Node> $nodes */
    private function collect(array $nodes, string $file, string $ns): void
    {
        $topLevel = [];
        foreach ($nodes as $n) {
            if ($n instanceof Stmt\Namespace_) {
                $this->collect($n->stmts ?? [], $file, $n->name?->toString() ?? '');
                continue;
            }
            if ($n instanceof Stmt\Function_) {
                $this->put('\\' . ($ns !== '' ? $ns . '\\' : '') . $n->name->toString(), $n->stmts ?? [], $n->params, $file);
                continue;
            }
            if ($n instanceof Stmt\Class_ || $n instanceof Stmt\Trait_ || $n instanceof Stmt\Interface_) {
                $cls = '\\' . ($ns !== '' ? $ns . '\\' : '') . ($n->name?->toString() ?? 'anonymous');
                foreach ($n->stmts as $m) {
                    if ($m instanceof Stmt\ClassMethod) {
                        $this->put($cls . '::' . $m->name->toString(), $m->stmts ?? [], $m->params, $file);
                    }
                }
                continue;
            }
            $topLevel[] = $n;
        }
        if ($topLevel !== []) {
            $this->put("<file:$file>", $topLevel, [], $file);
        }
    }

    /** @param list<Node> $stmts @param list<Node\Param> $params */
    private function put(string $fqn, array $stmts, array $params, string $file): void
    {
        $this->symbols[$fqn] = ['stmts' => $stmts, 'params' => $params, 'file' => $file];
    }

    /** @return list<string> */
    public function fqns(): array
    {
        $k = array_keys($this->symbols);
        sort($k, SORT_STRING); // determinisme (NFR-1)
        return $k;
    }

    public function has(string $fqn): bool { return isset($this->symbols[$fqn]); }

    /** @return array{stmts:list<Node>,params:list<Node\Param>,file:string} */
    public function get(string $fqn): array { return $this->symbols[$fqn]; }

    /** Resolution d'un nom d'appel simple vers un fqn connu. */
    public function resolveFunction(string $name): ?string
    {
        $candidates = [$name, '\\' . ltrim($name, '\\')];
        foreach ($candidates as $c) {
            if (isset($this->symbols[$c])) { return $c; }
        }
        // fonction appelee sans namespace depuis un fichier avec namespace
        $short = '\\' . ltrim(substr($name, (int) strrpos($name, '\\')), '\\');
        return isset($this->symbols[$short]) ? $short : null;
    }

    /** Resolution d'une methode : on cherche le suffixe ::nom sur les classes connues. */
    public function resolveMethod(string $method): ?string
    {
        $hits = [];
        foreach ($this->fqns() as $fqn) {
            if (str_ends_with($fqn, '::' . $method)) { $hits[] = $fqn; }
        }
        // Ambigu : ne pas deviner. Un seul candidat, on resout.
        return count($hits) === 1 ? $hits[0] : null;
    }
}
