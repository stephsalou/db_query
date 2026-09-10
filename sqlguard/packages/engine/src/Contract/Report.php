<?php declare(strict_types=1);

namespace SqlGuard\Engine\Contract;

use SqlGuard\Engine\Domain\Alert;
use SqlGuard\Engine\Domain\LimitRecorder;

/**
 * Contrat S-2 (AD-11) : unique amont de TOUS les rendus (AD-12).
 * `witness_level` est le litteral 1 ; aucune autre valeur n'est valide (AD-17).
 */
final class Report
{
    public const SCHEMA_VERSION = '1.0';
    public const TOOL_VERSION   = '0.1.0';

    /** @param list<Alert> $alerts */
    public function __construct(
        private readonly array $alerts,
        private readonly LimitRecorder $limits,
        private readonly string $rulePackId,
        private readonly string $rulePackVersion,
        private readonly int $filesIncluded,
        private readonly int $filesExcluded,
        private readonly string $phpTarget = '8.2',
        private readonly ?string $commit = null,
        /** @var list<string> identifiants d'alerte de la reference (AD-16) */
        private readonly array $baseline = [],
    ) {}

    /** Alertes non presentes dans la reference : les seules qui pesent sur le verdict. */
    public function newAlerts(): array
    {
        return array_values(array_filter(
            $this->alerts,
            fn(Alert $a): bool => !in_array($a->id, $this->baseline, true),
        ));
    }

    public function isComplete(): bool { return $this->limits->isEmpty(); }

    /** @return list<Alert> */
    public function alerts(): array { return $this->alerts; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $alerts = [];
        foreach ($this->alerts as $a) {
            $alerts[] = [
                'id'         => $a->id,
                'rule_id'    => $a->sinkRef->ruleId,
                'sink'       => [
                    'symbol_fqn' => $a->sinkRef->symbolFqn,
                    'sink_kind'  => $a->sinkRef->sinkKind,
                    'ordinal'    => $a->sinkRef->ordinal,
                ],
                'position'   => $a->position->toArray(),
                'witness'    => ['level' => 1, 'chain' => $a->witness->toArray()],
                // Jamais effacee : marquee (AD-16).
                'suppressed' => in_array($a->id, $this->baseline, true),
            ];
        }
        // Ordre deterministe (NFR-1) : par identifiant.
        usort($alerts, fn(array $x, array $y): int => strcmp($x['id'], $y['id']));

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'tool_version'   => self::TOOL_VERSION,
            'rule_pack'      => ['id' => $this->rulePackId, 'version' => $this->rulePackVersion],
            'php_target'     => $this->phpTarget,
            'commit'         => $this->commit,
            'status'         => $this->isComplete() ? 'complete' : 'incomplete',
            'witness_level'  => 1,
            'scope'          => [
                'root' => '.',
                'files_included' => $this->filesIncluded,
                'files_excluded' => $this->filesExcluded,
            ],
            'alerts'         => $alerts,
            'limits'         => $this->limits->toArray(),
        ];
    }

    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n";
    }
}
