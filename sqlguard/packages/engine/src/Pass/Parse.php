<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\ParserFactory;
use SqlGuard\Engine\Domain\LimitRecorder;
use SqlGuard\Engine\Domain\Position;

/**
 * Passe Parse. N'EXECUTE JAMAIS le code analyse : ni eval, ni include, ni
 * instanciation (AD-18, NFR-5). Une erreur de syntaxe devient une limite
 * d'analyse, jamais une exception qui interrompt le scan.
 */
final class Parse
{
    private readonly \PhpParser\Parser $parser;

    public function __construct(private readonly LimitRecorder $limits)
    {
        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    /** @return list<Node>|null null si le fichier n'a pas pu etre analyse */
    public function parse(string $code, string $canonicalFile): ?array
    {
        try {
            return $this->parser->parse($code) ?? [];
        } catch (Error $e) {
            $this->limits->record(
                LimitRecorder::PARSE_ERROR,
                $e->getRawMessage(),
                new Position($canonicalFile, max(1, $e->getStartLine())),
            );
            return null;
        }
    }
}
