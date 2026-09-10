# db_query

db_query is a simple SQL query builder on top of PDO. Values always go through
prepared statements; identifiers (tables, columns) go through a strict
allowlist.

Requires PHP >= 7.4 and the PDO extension.

## Table of Contents

- [initialisation](#initialisation)
- [select](#select)
- [where](#where)
- [insert](#insert)
- [update](#update)
- [delete](#delete)
- [sub_query](#sub_query)
- [raw sql](#raw-sql)

## initialisation

```php
require 'vendor/autoload.php';          // Composer
// ou, sans Composer :
// require 'autoload.php'; \steph\db_query\autoload::register();

use steph\db_query\db_query;

$query = new db_query('localhost', 'test_db', 'root', 'secret');
```

In an MVC project you can define the four constants once and construct with no
arguments:

```php
define('DATABASE_HOST', 'localhost');
define('DATABASE_NAME', 'test_db');
define('DATABASE_USER', 'root');
define('DATABASE_PASSWORD', 'secret');

$query = new db_query();
```

A missing constant raises a `DatabaseException` naming it. A failed connection
raises a `DatabaseException` too — the PDO message goes to `error_log()`, never
to the response.

Optional fifth argument: `['charset' => 'utf8mb4', 'port' => 3307]`.

To reuse an existing connection (tests, pooling):

```php
$query = db_query::withPdo($pdo);
```

## select

```php
$rows = $query->select('*')->from('user')->run_fetch(true)->getResult();
$one  = $query->select('id,first_name')->from('user')->run_fetch()->getResult();
```

`run_fetch()` returns `null` when there is no row (not `false`).

## where

Each condition is an array. The first may be `[column, value]`; any following
one needs an explicit `AND`/`OR`: `[glue, column, value]`.

```php
$query->select('*')->from('user')
      ->where([['id', 7], ['OR', 'first_name', 'bob']])
      ->run_fetch(true)->getResult();
```

Values are bound, never concatenated — `getSql()` shows `?` placeholders and
`getBindings()` the values. `where(null)` and `where([])` add nothing: there is
no implicit `WHERE 1`.

## insert

```php
$id = $query->insert('user', 'first_name,last_name', [
    ['bob', 'martin'],
    ['alice', 'dupont'],
])->insert_run()->getResult();
```

Every row must have exactly as many values as there are columns. `getResult()`
is the last insert id, or `null` when the table has no auto-increment.

## update

```php
$affected = $query->update('user', 'first_name', ['bob'])
                  ->where([['id', 7]])
                  ->run()->getResult();
```

`run()` returns the number of affected rows.

## delete

```php
$query->delete()->from('user')->where([['id', 7]])->run();
```

A `DELETE` or `UPDATE` with no `WHERE` is refused. To do it on purpose:

```php
$query->delete()->from('user')->allowUnbounded()->run();
```

## sub_query

```php
$query->select('*')->from('user')
      ->where([['active', 1]])
      ->open_sub_query('id', 'user')
          ->select('user_id')->from('orders')
      ->close_sub_query()
      ->run_fetch(true)->getResult();
```

`open_sub_query($column, $table)` qualifies a column of the **outer** query
(`user`.`id` here) and opens `IN (`; the nested `select()`/`from()` fill the
sub-query. An unbalanced sub-query is refused at execution.

## raw sql

`setSql()` bypasses every guard above. Never pass user input to it.

```php
$query->setSql('SELECT * FROM user WHERE id = ?', [7])->run_fetch()->getResult();
```

## tests

```
composer test        # ou : php tests/test_db_query.php
```
