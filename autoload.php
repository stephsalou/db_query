<?php

namespace steph\db_query;

/**
 * Chargeur de secours pour les projets sans Composer. Avec Composer, le
 * mapping PSR-4 de composer.json suffit et ce fichier est inutile.
 */
class autoload
{
    private const PREFIX = 'steph\\db_query\\';

    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        // Decliner les classes qui ne nous appartiennent pas, sinon un require
        // en echec ici casse les autres autoloaders de la chaine.
        if (strpos($class, self::PREFIX) !== 0) {
            return;
        }
        $relative = substr($class, strlen(self::PREFIX));
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
}
