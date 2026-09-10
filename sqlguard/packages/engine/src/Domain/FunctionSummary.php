<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Resume interprocedural (AD-6). Contenu FIXE : deux implementations
 * concurrentes ne doivent pas retenir des informations differentes.
 */
final class FunctionSummary
{
    /**
     * @param array<int,array{name:string,reaches_return:bool,reaches_sink:list<SinkRef>,sanitized_by:list<string>,hop_template:list<string>}> $params
     * @param list<string> $unresolved codes de LimitRecorder
     */
    public function __construct(
        public readonly string $symbolFqn,
        public readonly array $params,
        public readonly bool $returnsTaintedUnconditionally = false,
        /** @var list<string> ecritures de proprietes / statiques */
        public readonly array $sideTaints = [],
        public readonly array $unresolved = [],
    ) {}

    public static function empty(string $fqn): self
    {
        return new self($fqn, []);
    }

    public function paramReachesReturn(int $index): bool
    {
        return $this->params[$index]['reaches_return'] ?? false;
    }

    /** @return list<SinkRef> */
    public function paramReachesSink(int $index): array
    {
        return $this->params[$index]['reaches_sink'] ?? [];
    }

    /** @return list<string> */
    public function paramSanitizedBy(int $index): array
    {
        return $this->params[$index]['sanitized_by'] ?? [];
    }

    /** Signature pour detecter la convergence du fixpoint (AD-6). */
    public function signature(): string
    {
        $p = [];
        foreach ($this->params as $i => $d) {
            $p[] = $i . ':' . ($d['reaches_return'] ? '1' : '0')
                . ':' . count($d['reaches_sink'])
                . ':' . implode(',', $d['sanitized_by']);
        }
        return $this->symbolFqn . '|' . implode(';', $p)
            . '|' . ($this->returnsTaintedUnconditionally ? '1' : '0')
            . '|' . implode(',', $this->unresolved);
    }
}
