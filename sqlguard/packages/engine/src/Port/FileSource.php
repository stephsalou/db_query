<?php declare(strict_types=1);

namespace SqlGuard\Engine\Port;

/**
 * Port du systeme de fichiers. Le moteur ne connait que ce contrat : il ne
 * depend d'aucun adaptateur (AD-2), et n'ouvre aucun port reseau (AD-18).
 */
interface FileSource
{
    /** @return iterable<string> chemins canoniques relatifs a la racine */
    public function phpFiles(): iterable;

    public function read(string $canonicalPath): string;

    public function root(): string;
}
