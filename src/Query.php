<?php
/**
 * Created by PhpStorm.
 * User: stephane
 * Date: 26/04/2019
 * Time: 15:25
 */

namespace steph\db_query;

use Exception;
use PDOException;
use steph\db_query\Exception\StephQBUILDEREXCEPTION;

class Query
{
    protected $sql = '';
    protected $DB;
    protected $result;
    protected $sub_query;

    /**
     * db_query constructor.
     *
     * Array of database configuration
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        try {
            $host = (array_key_exists('host', $config) && !empty($config['host'])) ? $config['host'] : getenv('DATABASE_HOST');
            $name = (array_key_exists('name', $config) && !empty($config['name'])) ? $config['name'] : getenv('DATABASE_NAME');
            $user = (array_key_exists('user', $config) && !empty($config['user'])) ? $config['user'] : getenv('DATABASE_USER');
            $password = (array_key_exists('password', $config) && !empty($config['password'])) ? $config['password'] : getenv('DATABASE_PASSWORD');
            $this->DB = new \PDO('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8', $user, $password, array(
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ));
        } catch (PDOException $e) {
            throw $e;
        }
        $this->sql = '';
        $this->result = null;
        $this->sub_query = null;
    }


    /**
     * @param string $db_column
     *
     * @return Query
     * @throws Exception
     */
    public function select(string $db_column = '*'): Query
    {
        try {
            $this->sql = $this->sql !== '' ? $this->sql . '(SELECT ' . $db_column . ' ' : 'SELECT ' . $db_column . ' ';
            return $this;
        } catch (Exception $exception) {
            throw $exception;
        }

    }

    /**
     * @param string $db_table la table ou les donnes doivent etre prelever
     *
     * @return Query instance en cours
     * @throws StephQBUILDEREXCEPTION
     */
    public function from(string $db_table): Query
    {
        try {
            if (preg_match('/^(select|\(select)/i', $this->sql) || preg_match('/^(delete)/i', $this->sql)) {
                if (!empty($db_table)):
                    $this->sql .= ' FROM ' . $db_table;
                else:
                    throw new StephQBUILDEREXCEPTION('unknow database(data table) parameters');
                endif;
            } else {
                throw new StephQBUILDEREXCEPTION('cannot use `from` outside query');
            }
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }

    }

    /**
     * ajout de where dans la requete preparer
     *
     * @param array|null $cond condition dans la requette sql sous form de tableau associatif
     *
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function where(array $cond = null): Query
    {
        try {
            if (preg_match('/^(select|\(select)/i', $this->sql) || preg_match('/^(delete)/i', $this->sql) || preg_match('/^(INSERT)/i', $this->sql) || preg_match('/^(UPDATE)/i', $this->sql)) {
                if ($cond === null || empty($cond)) {
                    $this->sql .= ' WHERE 1';
                } elseif (count($cond) >= 1) {
                    foreach ($cond as $key => $conditions) {
                        if (count($conditions) === 3 && preg_match('/(where)/i', $this->sql)):
                            $this->sql .= ' ' . $conditions[0] . ' ' . $conditions[1] . '=\'' . $conditions[2] . '\'';
                        elseif (count($conditions) === 2 && (!preg_match('/(where)/i', $this->sql) || preg_match('/\(select/i', $this->sql))):
                            $this->sql .= ' WHERE ' . $conditions[0] . '=\'' . $conditions[1] . '\'';
                        else :
                            throw new StephQBUILDEREXCEPTION('except 2 or three parameters on where clauses');
                        endif;
                    }
                }
            } else {
                throw new StephQBUILDEREXCEPTION('must be give an sql a empty string given');
            }
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }

    }


    /**
     * @param string $db_table
     * @param string $data_column
     * @param array $values
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     * @throws Exception
     */
    public function insert(string $db_table, string $data_column, array $values): Query
    {
        try {
            if ($this->sql === '') {
                $req_values = '';
                $arr = explode(',', $data_column);
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $data_column = $this->escape_data($arr);
                $is_ok = true;
                foreach ($values as $key => $v) if (count($v) < count($arr) || count($v) % count($arr) !== 0) {
                    $is_ok = false;
                }
                if ($is_ok) {

                    foreach ($values as $keys => $vals) {
                        $str = $this->escape_data($vals, 'data');
                        $req_values = $keys === 0 ? 'VALUES (' . $str . ')' : $req_values . ', (' . $str . ')';
                    }
                    unset($str);
                    $this->sql .= 'INSERT INTO' . $db_table . ' (' . $data_column . ') ' . $req_values;
                } else {
                    throw new StephQBUILDEREXCEPTION('BAD parameter number given on insert query');
                }

                unset($arr);


            } else {
                throw new StephQBUILDEREXCEPTION('cannot use INSERT on sub-query');
            }
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        } catch (Exception $e) {
            throw $e;
        }

    }

    /**
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function delete(): Query
    {
        try {
            if ($this->sql === '') :
                $this->sql .= 'DELETE ';
            else :
                throw new StephQBUILDEREXCEPTION('Delete methods need empty SQL Query');
            endif;
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }


    }

    /**
     * @param string $table
     * @param string $data_column
     * @param array $values
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function update(string $table, string $data_column, array $values): Query
    {
        try {
            if ($this->sql === '') {
                $this->sql = 'update ' . $table . ' ';
                $arr = explode(',', $data_column);
                foreach ($arr as $keys => $val) {
                    $this->sql = $keys === 0 ? $this->sql . 'SET ' . $val . "=$values[$keys] " : $this->sql . ', ' . $val . "=$values[$keys] ";
                }
            } else {
                throw new StephQBUILDEREXCEPTION('Update methods need empty SQL Query');
            }
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }

    }

    /**
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function run(): Query
    {

        try {
            if (($this->sql !== '') && !empty($this->DB)):
                $statement = $this->DB->query($this->sql);
                $this->result = ($statement) ? true : false;
            else:
                throw new StephQBUILDEREXCEPTION('must have a sql query to run it');
            endif;
            $this->sql = '';
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }

    }

    /**
     * @param bool $all
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function run_fetch(bool $all = false): Query
    {
        try {
            if ($all) {
                if ($this->sql !== '' && !empty($this->DB)) :
                    $statement = $this->DB->query($this->sql);
                    $this->result = ($statement) ? $statement->fetchAll() : false;
                else :
                    throw new StephQBUILDEREXCEPTION('must have a sql query to run it');
                endif;
            } else {
                if ($this->sql !== '' && !empty($this->DB)):
                    $statement = $this->DB->query($this->sql);
                    $this->result = ($statement) ? $statement->fetch() : false;
                else :
                    throw new StephQBUILDEREXCEPTION('must have a sql query to run it');
                endif;
            }
            $this->sql = '';
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }

    }

    /**
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function insert_run(): Query
    {
        try {
            if (preg_match('/^(insert)/i', $this->sql) && !empty($this->sql) && !empty($this->DB)) :
                $statement = $this->DB->query($this->sql);
                $this->result = ($statement) ? $this->DB->lastInsertId() : false;
            else :
                throw new StephQBUILDEREXCEPTION('must have a sql query to run it');
            endif;
            $this->sql = '';
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }

    }

    /**
     * @return mixed
     */
    public function getDB(): \PDO
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


    /**
     * @param string $column
     * @param string $contrainte
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function open_sub_query(string $column, string $contrainte): Query
    {
        try {

            if (preg_match('/where +`+([a-zA-Z0-9])+`+/i', $this->sql)) :
                $this->sql .= 'AND ' . $contrainte . '.' . $column . ' IN ';
            else :
                throw new StephQBUILDEREXCEPTION('must have start a where condition to use sub-query ');
            endif;
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }
    }

    /**
     * @return Query
     * @throws StephQBUILDEREXCEPTION
     */
    public function close_sub_query(): Query
    {
        try {

            if (preg_match('/where +`+([a-zA-Z0-9])+`+/i', $this->sql) && preg_match('/(in\s\(select{1}?\s+`+[a-zA-Z0-9]+`+)/i', $this->sql)):
                $this->sql .= ' ) ';
            else:
                throw new StephQBUILDEREXCEPTION('no sub-query are open');
            endif;
            return $this;
        } catch (StephQBUILDEREXCEPTION $exception) {
            throw $exception;
        }
    }

    /**
     * @param string $sql
     *
     * @return query
     */
    public function setSql(string $sql): Query
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * @param array|string $data
     * @param string $type
     * @return array|string
     * @throws Exception
     */
    protected function escape_data(array $data, string $type = 'column')
    {
        try {
            $arr = $data;
            switch ($type) {
                case 'column':
                    foreach ($arr as $keys => $value) addslashes(($arr[$keys] = '`' . $value . '`'));
                    $data = implode(',', $arr);
                    break;
                case 'data':
                    foreach ($arr as $keys => $value) addslashes(($arr[$keys] = '\'' . $value . '\''));
                    $data = implode(',', $arr);
                    break;
                default:
                    foreach ($arr as $keys => $value) addslashes(($arr[$keys] = '`' . $value . '`'));
                    $data = $arr;
                    break;
            }
            return $data;
        } catch (Exception $exception) {
            throw $exception;
        }

    }

}