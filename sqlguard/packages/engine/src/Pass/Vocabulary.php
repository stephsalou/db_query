<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

/**
 * Sources, assainisseurs et sinks reconnus. Volontairement declaratif et
 * ferme : un paquet de regles pourra l'etendre (AD-23), pas le code appelant.
 */
final class Vocabulary
{
    /** Superglobales integralement non fiables. */
    public const SOURCE_GLOBALS = ['_GET', '_POST', '_REQUEST', '_COOKIE', '_FILES'];

    /**
     * `$_SERVER` est mixte : traiter toutes ses cles comme non fiables produit
     * des faux positifs sur `DOCUMENT_ROOT` ou `SCRIPT_NAME`, qui viennent du
     * serveur. Seules ces cles-la sont controlables par le client, plus tout
     * prefixe `HTTP_` (en-tetes de requete).
     *
     * Defaut identifie par la comparaison mesuree : aucun cas du corpus ne le
     * revelait. Voir docs/comparaison-mesuree.md.
     */
    public const SERVER_CONTROLLABLE = [
        'QUERY_STRING', 'REQUEST_URI', 'PATH_INFO', 'ORIG_PATH_INFO',
        'PHP_AUTH_USER', 'PHP_AUTH_PW', 'PHP_AUTH_DIGEST', 'AUTH_TYPE',
        'CONTENT_TYPE', 'REQUEST_METHOD', 'PHP_SELF', 'argv',
    ];

    public static function isServerKeyControllable(string $key): bool
    {
        return str_starts_with($key, 'HTTP_')
            || in_array($key, self::SERVER_CONTROLLABLE, true);
    }

    /**
     * Fonctions qui rompent la propagation SI leur valeur de retour est
     * conservee. C'est precisement la nuance qu'`escape_data()` de ce depot
     * avait manquee — voir la regle `sanitizer-noop`.
     */
    public const SANITIZERS = [
        'intval' => 'int', 'floatval' => 'float', 'boolval' => 'bool',
    ];

    /**
     * Echappeurs qui ne SUFFISENT PAS pour du SQL, mais dont l'appel avec
     * valeur de retour jetee reste le defaut `sanitizer-noop`.
     *
     * `addslashes()` n'echappe pas selon le jeu de caracteres de la connexion
     * et reste contournable (GBK). `mysqli_real_escape_string()` est correct
     * mais ne protege pas un identifiant ni une valeur non quotee. Aucun des
     * deux ne rompt la propagation.
     *
     * Etabli par comparaison mesuree : Psalm signalait cet usage, notre corpus
     * l'annotait propre a tort. Voir corpus/comparaison.json.
     */
    public const WEAK_ESCAPERS = [
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
