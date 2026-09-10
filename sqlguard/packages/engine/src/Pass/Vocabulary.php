<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

/**
 * Sources, assainisseurs et sinks reconnus. Volontairement declaratif et
 * ferme : un paquet de regles pourra l'etendre (AD-23), pas le code appelant.
 */
final class Vocabulary
{
    /** Superglobales non fiables. $_SERVER est inclus : nombre de ses cles sont controlables. */
    public const SOURCE_GLOBALS = ['_GET', '_POST', '_REQUEST', '_COOKIE', '_FILES', '_SERVER'];

    /**
     * Fonctions qui rompent la propagation SI leur valeur de retour est
     * conservee. C'est precisement la nuance qu'`escape_data()` de ce depot
     * avait manquee — voir la regle `sanitizer-noop`.
     */
    public const SANITIZERS = [
        'intval' => 'int', 'floatval' => 'float', 'boolval' => 'bool',
        'addslashes' => 'sql-quote-partial',
        'mysqli_real_escape_string' => 'sql-quote-partial',
        'mysqli_escape_string' => 'sql-quote-partial',
    ];

    /** Methodes d'echappement sur objet, meme nuance de valeur de retour. */
    public const SANITIZER_METHODS = ['quote' => 'sql-quote'];

    /** Casts qui rendent une valeur inoffensive pour du SQL. */
    public const SAFE_CASTS = ['int', 'integer', 'float', 'double', 'bool', 'boolean'];

    /**
     * Methodes PDO/PDOStatement connues et sans effet sur la propagation.
     * Sans cette liste, un usage parametre correct produirait une limite
     * `unknown_callee` et ferait sortir tout scan propre en « incomplet » —
     * rendant le code de sortie inutilisable.
     */
    public const KNOWN_DB_METHODS = [
        'execute', 'fetch', 'fetchall', 'fetchcolumn', 'fetchobject',
        'bindvalue', 'bindparam', 'rowcount', 'lastinsertid', 'columncount',
        'closecursor', 'setattribute', 'getattribute', 'begintransaction',
        'commit', 'rollback', 'intransaction', 'errorinfo', 'errorcode',
    ];

    /** Sinks base. V1 : PDO uniquement (FR-3). */
    public const SINK_METHODS = [
        'query'   => 'pdo.query',
        'exec'    => 'pdo.exec',
        'prepare' => 'pdo.prepare_dynamic',
    ];
}
