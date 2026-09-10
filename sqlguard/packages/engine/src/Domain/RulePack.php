<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Manifeste de regles unique (AD-8) : cle de jointure de tout le systeme.
 * Le meme fichier alimente le moteur ET les pages de documentation.
 */
final class RulePack
{
    /** @param array<string,array<string,mixed>> $rules indexe par id */
    private function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly array $rules,
    ) {}

    public static function fromManifestFile(string $path, string $id = 'generic'): self
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("manifeste de regles introuvable : $path");
        }
        /** @var array{version?:int,rules?:list<array<string,mixed>>} $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $rules = [];
        foreach ($data['rules'] ?? [] as $r) {
            if (!isset($r['id'])) {
                throw new \RuntimeException('regle sans id dans le manifeste');
            }
            $rules[(string) $r['id']] = $r;
        }
        if ($rules === []) {
            throw new \RuntimeException('manifeste de regles vide');
        }
        return new self($id, ((string) ($data['version'] ?? 1)) . '.0.0', $rules);
    }

    public function major(): int { return (int) explode('.', $this->version)[0]; }

    public function has(string $ruleId): bool { return isset($this->rules[$ruleId]); }

    public function level(string $ruleId): int { return (int) ($this->rules[$ruleId]['level'] ?? 1); }

    public function title(string $ruleId): string { return (string) ($this->rules[$ruleId]['title'] ?? $ruleId); }

    /** @return list<string> */
    public function ids(): array { return array_keys($this->rules); }
}
