<?php

namespace steph\db_query\Exception;

/**
 * Toutes les erreurs de db_query. Remplace l'ancien trait Database_Exception,
 * qui exposait Error()/showErr()/$e sur chaque instance et renvoyait
 * l'exception precedente quand le code d'erreur etait inconnu.
 */
class DatabaseException extends \RuntimeException
{
    public const NO_CONNECTION      = 1;
    public const EMPTY_QUERY        = 2;
    public const BAD_USAGE          = 3;
    public const BAD_IDENTIFIER     = 4;
    public const BAD_PARAMETERS     = 5;
    public const UNBOUNDED_STATEMENT = 6;
}
