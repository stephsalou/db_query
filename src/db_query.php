<?php

namespace steph\db_query;

use steph\db_query\Exception\DatabaseException;

/**
 * Constructeur de requetes SQL sur PDO.
 *
 * Les valeurs ne sont JAMAIS concatenees dans le SQL : elles sont accumulees
 * dans $bindings et passees a PDOStatement::execute(). Les identifiants
 * (tables, colonnes) ne peuvent pas etre parametres en SQL, ils passent donc
 * par une liste blanche stricte -- voir quoteIdent().
 */
class db_query
{
    protected ?\PDO $DB = null;
    protected string $sql = '';
    protected array $bindings = [];
    protected $result = null;

    /** Un WHERE existe-t-il deja, par niveau de sous-requete. */
    protected array $hasWhere = [0 => false];
    protected int $depth = 0;

    /** Autorise un DELETE/UPDATE sans WHERE pour le prochain run(). */
    protected bool $allowUnbounded = false;

    /**
     * Les quatre parametres tombent sur les constantes DATABASE_* quand elles
     * sont definies, ce qui garde l'usage sans argument documente dans le
     * readme. Elles ne sont plus des valeurs par defaut de parametres : une
     * constante indefinie y provoquait une Error fatale avant meme l'entree
     * dans le corps de la methode.
     */
    public function __construct(
        ?string $host = null,
        ?string $name = null,
        ?string $user = null,
        ?string $pwd = null,
        array $options = []
    ) {
        $host = $host ?? self::configured('DATABASE_HOST');
        $name = $name ?? self::configured('DATABASE_NAME');
        $user = $user ?? self::configured('DATABASE_USER');
        $pwd  = $pwd  ?? self::configured('DATABASE_PASSWORD');

        $charset = $options['charset'] ?? 'utf8mb4';
        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;
        if (isset($options['port'])) {
            $dsn .= ';port=' . (int) $options['port'];
        }

        try {
            $this->DB = new \PDO($dsn, $user, $pwd, [
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (\PDOException $e) {
            // Le message PDO contient le DSN et l'utilisateur : il va au log,
            // jamais dans la reponse.
            error_log('db_query: ' . $e->getMessage());
            throw new DatabaseException(
                'database connection failed',
                DatabaseException::NO_CONNECTION,
                $e
            );
        }
    }

    /** Injecte une connexion existante (tests, pool, autre driver). */
    public static function withPdo(\PDO $pdo): self
    {
        $q = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        $q->DB = $pdo;
        $q->reset();
        return $q;
    }

    private static function configured(string $const): string
    {
        if (!defined($const)) {
            throw new DatabaseException(
                $const . ' is not configured: pass it to the constructor or define the constant',
                DatabaseException::BAD_PARAMETERS
            );
        }
        return (string) constant($const);
    }

    // ---------------------------------------------------------------- identifiants

    /**
     * Liste blanche, pas echappement : un identifiant qui n'est pas un simple
     * nom (eventuellement qualifie table.colonne) est refuse. C'est la seule
     * defense possible, un identifiant ne pouvant pas etre un parametre lie.
     */
    protected function quoteIdent(string $name): string
    {
        $name = trim($name);
        $parts = explode('.', $name);
        $out = [];
        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z0-9_$]+$/', $part)) {
                throw new DatabaseException(
                    'illegal identifier: ' . $name,
                    DatabaseException::BAD_IDENTIFIER
                );
            }
            $out[] = '`' . $part . '`';
        }
        return implode('.', $out);
    }

    /** Liste de colonnes separee par des virgules, ou '*'. */
    protected function quoteColumns(string $columns): string
    {
        $columns = trim($columns);
        if ($columns === '*') {
            return '*';
        }
        $out = [];
        foreach (explode(',', $columns) as $col) {
            $col = trim($col);
            $out[] = ($col === '*') ? '*' : $this->quoteIdent($col);
        }
        if ($out === []) {
            throw new DatabaseException('empty column list', DatabaseException::BAD_PARAMETERS);
        }
        return implode(', ', $out);
    }

    // ---------------------------------------------------------------- construction

    public function select(string $db_column = '*'): object
    {
        $cols = $this->quoteColumns($db_column);
        if ($this->sql === '') {
            $this->sql = 'SELECT ' . $cols;
        } elseif ($this->inOpenSubQuery()) {
            $this->sql .= 'SELECT ' . $cols;
        } else {
            throw new DatabaseException(
                'cannot start a SELECT on a query already in progress',
                DatabaseException::BAD_USAGE
            );
        }
        return $this;
    }

    public function from(string $db_table): object
    {
        if (!preg_match('/^\s*(select|delete)/i', $this->currentFragment())) {
            throw new DatabaseException(
                'cannot use `from` outside a SELECT or DELETE query',
                DatabaseException::BAD_USAGE
            );
        }
        if (trim($db_table) === '') {
            throw new DatabaseException(
                'unknown database(data table) parameters',
                DatabaseException::BAD_PARAMETERS
            );
        }
        $this->sql .= ' FROM ' . $this->quoteIdent($db_table);
        return $this;
    }

    /**
     * @param array|null $cond [[colonne, valeur], ...] pour la premiere
     *                         condition, [AND|OR, colonne, valeur] ensuite.
     *                         null ou [] ne pose plus WHERE 1 : voir
     *                         allowUnbounded() pour un DELETE/UPDATE global.
     */
    public function where(?array $cond = null): object
    {
        if (!preg_match('/^\s*(select|\(select|delete|insert|update)/i', $this->sql)
            && !$this->inOpenSubQuery()) {
            throw new DatabaseException(
                'cannot use `where` outside a query',
                DatabaseException::BAD_USAGE
            );
        }
        if ($cond === null || $cond === []) {
            // Pas de `WHERE 1` implicite : c'etait un DELETE/UPDATE global
            // declenche par un tableau de filtres vide.
            return $this;
        }

        foreach ($cond as $conditions) {
            if (!is_array($conditions)) {
                throw new DatabaseException(
                    'each condition must be an array',
                    DatabaseException::BAD_PARAMETERS
                );
            }
            $n = count($conditions);
            if ($n === 2) {
                [$column, $value] = $conditions;
                $glue = null;
            } elseif ($n === 3) {
                [$glue, $column, $value] = $conditions;
                $glue = strtoupper(trim((string) $glue));
                if (!in_array($glue, ['AND', 'OR'], true)) {
                    throw new DatabaseException(
                        'condition conjunction must be AND or OR',
                        DatabaseException::BAD_PARAMETERS
                    );
                }
            } else {
                throw new DatabaseException(
                    'except 2 or three parameters on where clauses',
                    DatabaseException::BAD_PARAMETERS
                );
            }
            if (!is_scalar($value) && $value !== null) {
                throw new DatabaseException(
                    'condition value must be scalar or null',
                    DatabaseException::BAD_PARAMETERS
                );
            }

            $keyword = $this->currentHasWhere() ? ($glue ?? 'AND') : 'WHERE';
            $this->sql .= ' ' . $keyword . ' ' . $this->quoteIdent((string) $column) . ' = ?';
            $this->bindings[] = $value;
            $this->markHasWhere();
        }
        return $this;
    }

    public function insert(string $db_table, string $data_column, array $values): object
    {
        if ($this->sql !== '') {
            throw new DatabaseException(
                'cannot use INSERT on a query already in progress',
                DatabaseException::BAD_USAGE
            );
        }
        $cols = array_map('trim', explode(',', $data_column));
        if ($cols === [] || $cols === ['']) {
            throw new DatabaseException('table and columns required', DatabaseException::BAD_PARAMETERS);
        }
        if ($values === []) {
            throw new DatabaseException('no value rows given', DatabaseException::BAD_PARAMETERS);
        }

        $quoted = implode(', ', array_map([$this, 'quoteIdent'], $cols));
        $groups = [];
        foreach (array_values($values) as $row) {
            if (!is_array($row)) {
                throw new DatabaseException(
                    'each value row must be an array',
                    DatabaseException::BAD_PARAMETERS
                );
            }
            if (count($row) !== count($cols)) {
                throw new DatabaseException(
                    'BAD parameter number given on insert query',
                    DatabaseException::BAD_PARAMETERS
                );
            }
            $groups[] = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';
            foreach (array_values($row) as $value) {
                $this->bindings[] = $value;
            }
        }

        $this->sql = 'INSERT INTO ' . $this->quoteIdent($db_table)
            . ' (' . $quoted . ') VALUES ' . implode(', ', $groups);
        return $this;
    }

    public function delete(): object
    {
        if ($this->sql !== '') {
            throw new DatabaseException('query must be empty', DatabaseException::BAD_USAGE);
        }
        $this->sql = 'DELETE ';
        return $this;
    }

    public function update(string $table, string $data_column, array $values): object
    {
        if ($this->sql !== '') {
            throw new DatabaseException('query must be empty', DatabaseException::BAD_USAGE);
        }
        $cols = array_map('trim', explode(',', $data_column));
        $vals = array_values($values);
        if (count($cols) !== count($vals)) {
            throw new DatabaseException(
                'column/value count mismatch',
                DatabaseException::BAD_PARAMETERS
            );
        }

        $sets = [];
        foreach ($cols as $i => $col) {
            $sets[] = $this->quoteIdent($col) . ' = ?';
            $this->bindings[] = $vals[$i];
        }
        $this->sql = 'UPDATE ' . $this->quoteIdent($table) . ' SET ' . implode(', ', $sets) . ' ';
        return $this;
    }

    // ---------------------------------------------------------------- sous-requetes

    public function open_sub_query(string $column, string $contrainte): object
    {
        if (!$this->currentHasWhere()) {
            throw new DatabaseException(
                'must have start a where condition to use sub-query',
                DatabaseException::BAD_USAGE
            );
        }
        $this->sql .= ' AND ' . $this->quoteIdent($contrainte . '.' . $column) . ' IN (';
        $this->depth++;
        $this->hasWhere[$this->depth] = false;
        return $this;
    }

    public function close_sub_query(): object
    {
        if ($this->depth < 1) {
            throw new DatabaseException('no sub-query are open', DatabaseException::BAD_USAGE);
        }
        $this->sql .= ' )';
        unset($this->hasWhere[$this->depth]);
        $this->depth--;
        return $this;
    }

    /** Le SQL depuis la derniere parenthese ouvrante : la clause en cours. */
    protected function currentFragment(): string
    {
        $open = strrpos($this->sql, '(');
        return trim($open === false ? $this->sql : substr($this->sql, $open + 1));
    }

    protected function inOpenSubQuery(): bool
    {
        return $this->depth > 0 && substr(rtrim($this->sql), -1) === '(';
    }

    protected function currentHasWhere(): bool
    {
        return $this->hasWhere[$this->depth] ?? false;
    }

    protected function markHasWhere(): void
    {
        $this->hasWhere[$this->depth] = true;
    }

    // ---------------------------------------------------------------- execution

    /** Autorise un seul DELETE/UPDATE sans WHERE. */
    public function allowUnbounded(): object
    {
        $this->allowUnbounded = true;
        return $this;
    }

    private function execute(): \PDOStatement
    {
        if ($this->DB === null) {
            throw new DatabaseException('no database connection', DatabaseException::NO_CONNECTION);
        }
        if ($this->sql === '') {
            throw new DatabaseException(
                'must have a sql query to run it',
                DatabaseException::EMPTY_QUERY
            );
        }
        if ($this->depth > 0) {
            throw new DatabaseException(
                'unbalanced sub-query: ' . $this->depth . ' still open',
                DatabaseException::BAD_USAGE
            );
        }
        if (preg_match('/^\s*(delete|update)/i', $this->sql)
            && !preg_match('/\bWHERE\b/i', $this->sql)
            && !$this->allowUnbounded) {
            throw new DatabaseException(
                'refusing unbounded DELETE/UPDATE; call allowUnbounded() to confirm',
                DatabaseException::UNBOUNDED_STATEMENT
            );
        }

        try {
            $statement = $this->DB->prepare($this->sql);
            $statement->execute($this->bindings);
            return $statement;
        } finally {
            // Doit tourner meme quand execute() leve, sinon la requete cassee
            // reste collee a la suivante.
            $this->reset();
        }
    }

    /** @return object $this ; getResult() renvoie le nombre de lignes touchees. */
    public function run(): object
    {
        $this->result = null;
        $this->result = $this->execute()->rowCount();
        return $this;
    }

    public function run_fetch(bool $all = false): object
    {
        $this->result = null;
        $statement = $this->execute();
        if ($all) {
            $this->result = $statement->fetchAll();
        } else {
            $row = $statement->fetch();
            $this->result = ($row === false) ? null : $row;
        }
        return $this;
    }

    public function insert_run(): object
    {
        if (!preg_match('/^\s*insert/i', $this->sql)) {
            throw new DatabaseException(
                'insert_run requires an INSERT query',
                DatabaseException::BAD_USAGE
            );
        }
        $this->result = null;
        $this->execute();
        $id = $this->DB->lastInsertId();
        $this->result = ($id === '0' || $id === false) ? null : $id;
        return $this;
    }

    protected function reset(): void
    {
        $this->sql = '';
        $this->bindings = [];
        $this->hasWhere = [0 => false];
        $this->depth = 0;
        $this->allowUnbounded = false;
    }

    // ---------------------------------------------------------------- accesseurs

    public function getDB(): \PDO
    {
        if ($this->DB === null) {
            throw new DatabaseException('no database connection', DatabaseException::NO_CONNECTION);
        }
        return $this->DB;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /** Les valeurs liees pour le SQL courant, dans l'ordre des placeholders. */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /** @return mixed */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * SQL brut, execute tel quel. Contourne toutes les gardes ci-dessus :
     * ne jamais y passer de donnee provenant d'une requete utilisateur.
     */
    public function setSql(string $sql, array $bindings = []): object
    {
        $this->reset();
        $this->sql = $sql;
        $this->bindings = $bindings;
        return $this;
    }

    public function __destruct()
    {
        $this->DB = null;
        $this->sql = '';
    }
}
