# db_query 🌋🌋🌋

db_query is a simple sql query creator .this is a simple and extensible library to write and execute sql query

db_query  requires PHP >= 7.2.

## Table of Contents

- [initialisation](#initialisation)
- [select](#select)
- [insert](#insert)
- [update](#update)
- [delete](#delete)
- [sub_query](#sub_query)
- [set_query](#set_query)


## initialisation

 USE `$query=new \steph\query\db_query($DB_host,$DB_name,$DB_user,$DB_password)`
 OR you can put all these informations in config file where you are a MVC architecture and initialise
 with `$query = new \steph\query\db_query()`.


## select

###### Usage of select query:
> database parameters :
* database host 'localhost' `string`
* database name 'test_db' `string`
* database user 'root' `string`
* database password 'secret' `string`

> table :
{
    user:'id,first_name,last_name,cellphone'
}


```
$query=new \steph\query\db_query('localhhost','test_DB','root','secret')
```
if you want to select all line:
```
$query->select('*')->from('user')->run_fetch(true)->getResult();
```
if you want to select first line: