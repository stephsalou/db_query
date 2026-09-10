<?php declare(strict_types=1);

namespace SqlGuard\Engine;

use SqlGuard\Engine\Contract\Report;
use SqlGuard\Engine\Domain\LimitRecorder;
use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Pass\Parse;
use SqlGuard\Engine\Pass\Propagate;
use SqlGuard\Engine\Pass\Summarize;
use SqlGuard\Engine\Pass\SymbolTable;
use SqlGuard\Engine\Port\FileSource;

/**
 * Chaine de passes fermee et ordonnee (AD-3) : Parse -> Propagate -> Judge.
 * Aucune passe n'en saute une autre ; aucune n'ecrit dans l'artefact d'une
 * passe amont.
 *
 * Surface @api (AD-19) : cette classe et Report. Rien d'autre n'est stable.
 * @api
 */
final class Scanner
{
    public function __construct(
        private readonly FileSource $files,
        private readonly RulePack $rulePack,
        /** @var list<string> */
        private readonly array $baseline = [],
    ) {}

    public function scan(): Report
    {
        $limits = new LimitRecorder();
        $parse  = new Parse($limits);

        // Passe 1 — Parse, une seule fois : les AST sont reutilises par les
        // passes suivantes plutot que reparsees.
        $asts = [];
        $included = 0;
        $excluded = 0;
        foreach ($this->files->phpFiles() as $canonical) {
            $ast = $parse->parse($this->files->read($canonical), $canonical);
            if ($ast === null) { $excluded++; continue; }
            $asts[$canonical] = $ast;
            $included++;
        }

        // Passe 2 — table des symboles, tous fichiers confondus : sans vue
        // globale, un appel inter-fichiers serait « non resolu » a tort.
        $symbols = new SymbolTable();
        foreach ($asts as $canonical => $ast) {
            $symbols->addFile($ast, $canonical);
        }

        // Passe 3 — resumes interproceduraux, SCC en ordre topologique inverse.
        $summaries = (new Summarize($symbols, $this->rulePack, $limits))->run();

        // Passe 4 — propagation et emission.
        $propagate = new Propagate($this->rulePack, $limits, $symbols);
        $propagate->withSummaries($summaries);
        foreach ($asts as $canonical => $ast) {
            $propagate->analyseFile($ast, $canonical);
        }

        return new Report(
            $propagate->alerts(),
            $limits,
            $this->rulePack->id,
            $this->rulePack->version,
            $included,
            $excluded,
            '8.2',
            null,
            $this->baseline,
        );
    }
}
