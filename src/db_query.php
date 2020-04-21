<?php
/**
 * Created by PhpStorm.
 * User: stephane
 * Date: 26/04/2019
 * Time: 15:25
 */
namespace steph\db_query;
include 'Exception/Database_Exception.php';

class db_query
{
    use \steph\db_query\Exception\Database_Exception;
    protected $sql = '';
    protected $DB;
    protected $result;
    protected $sub_query;

    /**
     * db_query constructor.
     *
     * @param string $host
     * @param string $name
     * @param string $user
     * @param string $pwd
     */
    public function __construct(string $host = DATABASE_HOST, string $name = DATABASE_NAME, string $user = DATABASE_USER, string $pwd = DATABASE_PASSWORD)
    {
        try {
            $this->DB = new \PDO('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8', $user, $pwd, array(
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION
            ));
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
        $this->sql = '';
        $this->result = null;
        $this->sub_query = null;
    }


    /**
     * @param string $db_column
     *
     * @return object
     */
    public function select(string $db_column = '*'):object
    {
        if ($this->sql != '') {
            $this->sql .= '(SELECT ' . $db_column . ' ';
        } else {
            $this->sql = 'SELECT ' . $db_column . ' ';
        }
        return $this;
    }

    /**
     * @param string $db_table la table ou les donnes doivent etre prelever
     *
     * @return object instance en cours
     * @throws \Exception
     */
    public function from(string $db_table):object
    {
        if (preg_match('/^(select|\(select)/i', $this->sql) || preg_match('/^(delete)/i', $this->sql)) {
            if (!empty($db_table)) {
                $this->sql .= ' FROM ' . $db_table;
            } else {
                $err = $this->Error(BAD_USING_OF_FROM, 'unknow database(data table) parameters');

                throw $err;
            }
        } else {
            $err = $this->Error(BAD_USING_OF_FROM);

            throw $err;
        }
        return $this;
    }

    /**
     * ajout de where dans la requete preparer
     *
     * @param array|null $cond condition dans la requette sql sous form de tableau associatif
     *
     * @return $this retourne l'objet lui meme pour un chaining
     * @throws Exception en cas d'erreur renvoie une exception du type database exception
     * @throws \Exception
     */


    public function where(array $cond = null):object
    {

        if (preg_match('/^(select|\(select)/i', $this->sql) || preg_match('/^(delete)/i', $this->sql) || preg_match('/^(INSERT)/i', $this->sql) || preg_match('/^(UPDATE)/i', $this->sql)) {
            if ($cond == null) {
                $this->sql .= ' WHERE 1';
            } elseif (sizeof($cond) >= 1) {
                foreach ($cond as $key => $conditions) {
                    if (sizeof($conditions) == 3 && preg_match('/(where)/i', $this->sql)) {
                        $this->sql .= ' ' . $conditions[0] . ' ' . $conditions[1] . '=\'' . $conditions[2] . '\'';
                    } elseif (sizeof($conditions) == 2 && (!preg_match('/(where)/i', $this->sql) || preg_match('/\(select/i', $this->sql))) {
                        $this->sql .= ' WHERE ' . $conditions[0] . '=\'' . $conditions[1] . '\'';
                    } else {
                        $err = $this->Error(NO_EMPTY_STRING_ACCEPT, 'except 2 or three parameters on where clauses');
                        throw $err;
                    }
                }
            }
        } else {
            $err = $this->Error(NO_EMPTY_STRING_ACCEPT);

            throw $err;
        }
        return $this;
    }

    private function escape_data(array $data, string $type = 'column')
    {
        $arr = $data;
        switch ($type) {
            case 'column':
                foreach ($arr as $keys => $value) {
                    addslashes(($arr[$keys] = '`' . $value . '`'));
                }
                $data = implode(',', $arr);
                break;
            case 'data':
                foreach ($arr as $keys => $value) {
                    addslashes(($arr[$keys] = '\'' . $value . '\''));
                }
                $data = implode(',', $arr);
                break;
            default:
                foreach ($arr as $keys => $value) {
                    addslashes(($arr[$keys] = '`' . $value . '`'));
                }
                $data = $arr;
                break;

        }

        return $data;
    }

    public function insert(string $db_table, string $data_column, array $values):object
    {
        if ($this->sql == '') {
            $req_values = '';
            $arr = explode(',', $data_column);
            $data_column = $this->escape_data($arr);
            $is_ok = true;
            foreach ($values as $key => $v) {
                if (sizeof($v) < sizeof($arr) || sizeof($v) % sizeof($arr) != 0) {
                    $is_ok = false;
                }
            }
            if ($is_ok) {

                foreach ($values as $keys => $vals) {
                    $str = $this->escape_data($vals, 'data');
                    if ($keys == 0) {
                        $req_values = 'VALUES (' . $str . ')';
                    } else {
                        $req_values .= ', (' . $str . ')';
                    }
                }
                unset($str);
                $this->sql .= 'INSERT INTO' . $db_table . ' (' . $data_column . ') ' . $req_values;
            } else {
                $err = $this->Error(INSERT_ERROR, 'BAD parameter number given on insert query');

                throw $err;
            }

            unset($arr);


        } else {
            $err = $this->Error(INSERT_ERROR);

            throw $err;
        }
        return $this;
    }

    public function delete():object
    {
        if ($this->sql == '') {
            $this->sql .= 'DELETE ';
        } else {
            $err = $this->Error(MUST_EMPTY_QUERY);

            throw $err;
        }
        return $this;
    }

    public function update(string $table, string $data_column,array $values):object
    {
        if ($this->sql == '') {
            $this->sql = 'update ' . $table . ' ';
            $arr = explode(',', $data_column);
            foreach ($arr as $keys => $val) {
                if ($keys == 0) {
                    $this->sql .= "SET " . $val . "=$values[$keys] ";
                } else {
                    $this->sql .= ", " . $val . "=$values[$keys] ";
                }
            }
        } else {
            $err = $this->Error(MUST_EMPTY_QUERY);

            throw $err;
        }
        return $this;
    }

    public function run():object
    {
        if ($this->sql != '' && !empty($this->DB)) {
            $statement = $this->DB->query($this->sql);
            $this->result = ($statement) ? true : false;
        } else {
            $err = $this->Error(NO_EMPTY_STRING_ACCEPT, 'must have a sql query to run it');
            throw $err;
        }
        $this->sql='';
        return $this;
    }

    public function run_fetch(bool $all = false):object
    {
        if ($all) {
            if ($this->sql != '' && !empty($this->DB)) {
                $statement = $this->DB->query($this->sql);
                $this->result = ($statement) ? $statement->fetchAll() : false;
            } else {
                $err = $this->Error(NO_EMPTY_STRING_ACCEPT, 'must have a sql query to run it');
                throw $err;
            }
        } else {
            if ($this->sql != '' && !empty($this->DB)) {
                $statement = $this->DB->query($this->sql);
                $this->result = ($statement) ? $statement->fetch() : false;
            } else {
                $err = $this->Error(NO_EMPTY_STRING_ACCEPT, 'must have a sql query to run it');
                throw $err;
            }
        }
        $this->sql='';
        return $this;
    }

    public function insert_run():object
    {
        if (preg_match('/^(insert)/i', $this->sql) && !empty($this->sql) && !empty($this->DB)) {
            $statement = $this->DB->query($this->sql);
            $this->result = ($statement) ? $this->DB->lastInsertId() : false;
        } else {
            $err = $this->Error(NO_EMPTY_STRING_ACCEPT, 'must have a sql query to run it');
            throw $err;
        }
        $this->sql='';
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDB():\PDO
    {
        return $this->DB;
    }

    /**
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    public function __destruct()
    {
        $this->DB = null;
        $this->sql = null;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }


    public function open_sub_query(string $column, string $contrainte):object
    {
        if (preg_match('/where +`+([a-zA-Z0-9])+`+/i', $this->sql)) {
            $this->sql .= 'AND ' . $contrainte . '.' . $column . ' IN ';
        } else {
            $err = $this->Error(BAD_USING_OF_SUB_QUERY);
            throw $err;
        }
        return $this;
    }

    public function close_sub_query():object
    {
        if (preg_match('/where +`+([a-zA-Z0-9])+`+/i', $this->sql) && preg_match('/(in\s\(select{1}?\s+`+[a-zA-Z0-9]+`+)/i', $this->sql)) {
            $this->sql .= ' ) ';
        } else {
            $err = $this->Error(BAD_USING_OF_SUB_QUERY, 'no sub-query are open');
            throw $err;
        }
        return $this;
    }

    /**
     * @param string $sql
     *
     * @return db_query
     */
    public function setSql(string $sql): object
    {
        $this->sql = $sql;
        return $this;
    }
}