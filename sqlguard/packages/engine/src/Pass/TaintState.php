<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

use SqlGuard\Engine\Domain\AccessPath;
use SqlGuard\Engine\Domain\Position;
use SqlGuard\Engine\Domain\Taint;

/** Etat de flux : application chemin d'acces -> treillis de marque (AD-4). */
final class TaintState
{
    /** @var array<string,Taint> */
    private array $map = [];
    /** @var array<string,Position> position de la source, pour le temoin */
    private array $origin = [];

    public function set(AccessPath $p, Taint $t, ?Position $at = null): void
    {
        $this->map[$p->key()] = $t;
        if ($at !== null) {
            $this->origin[$p->key()] = $at;
        }
    }

    public function get(AccessPath $p): Taint
    {
        if (isset($this->map[$p->key()])) {
            return $this->map[$p->key()];
        }
        // un conteneur agrege couvre ses sous-chemins
        $agg = AccessPath::aggregate($p->root)->key();
        return $this->map[$agg] ?? Taint::clean();
    }

    public function originOf(AccessPath $p): ?Position
    {
        return $this->origin[$p->key()] ?? $this->origin[AccessPath::aggregate($p->root)->key()] ?? null;
    }

    public function copy(): self
    {
        $c = new self();
        $c->map = $this->map;
        $c->origin = $this->origin;
        return $c;
    }

    /** Union au point de confluence (AD-4) : le plus haut des deux par chemin. */
    public function union(self $other): self
    {
        $u = $this->copy();
        foreach ($other->map as $k => $t) {
            $u->map[$k] = isset($u->map[$k]) ? $u->map[$k]->union($t) : $t;
        }
        foreach ($other->origin as $k => $p) {
            $u->origin[$k] ??= $p;
        }
        return $u;
    }

    /** @return array<string,int> signature pour detecter la convergence d'une boucle */
    public function signature(): array
    {
        $s = [];
        foreach ($this->map as $k => $t) { $s[$k] = $t->level; }
        ksort($s);
        return $s;
    }
}
