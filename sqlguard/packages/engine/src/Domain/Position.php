<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/** Forme canonique des positions (AD-25) : chemin relatif, `/`, lignes 1-based. */
final class Position
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly int $column = 1,
    ) {}

    /** Canonicalise un chemin par rapport a la racine du scan. */
    public static function canonicalPath(string $absolute, string $root): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $p    = str_replace('\\', '/', $absolute);
        if (str_starts_with($p, $root)) {
            $p = substr($p, strlen($root));
        }
        $p = preg_replace('#^\./#', '', $p) ?? $p;
        return \Normalizer::isNormalized($p) ? $p : (\Normalizer::normalize($p) ?: $p);
    }

    /** @return array{file:string,line:int,column:int} */
    public function toArray(): array
    {
        return ['file' => $this->file, 'line' => $this->line, 'column' => $this->column];
    }
}
