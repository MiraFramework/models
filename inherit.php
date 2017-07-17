<?php

namespace Mira;

$config = require_once $_SERVER['DOCUMENT_ROOT']."/config/config.php";

class Model
{
    public $database = null;
    public $arr = array();
    public $update = array();
    public $structure = array();
    private $db_engine;
    
    public function __construct($default_database = null)
    {
        if (isset($this->db)) {
            $this->database = $this->db;
        } elseif ($default_database === null) {
            echo("DID NOT SUPPLY DATABASE INSIDE INSTATIATED CLASS");
        } else {
            $this->database = $default_database;
        }

        // check if database exists
        global $config;
        $connection = 'mysql:host=localhost;';
        $this->db_engine = new \PDO(
            $connection,
            $config['database']['username'],
            $config['database']['password']
        );
        if ($this->create == true) {
            $this->db_engine->query("CREATE DATABASE IF NOT EXISTS ".$this->database);
        }

        $connection = 'mysql:host=localhost;dbname='.$this->database;
        $this->db_engine = new \PDO(
            $connection,
            $config['database']['username'],
            $config['database']['password']
        );

        $table = static::class;
        $query = $this->db_engine->query("SHOW TABLES LIKE '$table' ");
        
        // query the database
        // get the columns and echo them
        
        if ($this->create == true && $query->rowCount()) {
            // the table exists and create is true. try and add the columns
            $query = $this->db_engine->query("SELECT * FROM $table WHERE 1");
            $get_column = $query->GetColumnMeta(1);
            
            // match the get column ['name'] to each element in the array.
            $variables = get_class_vars(static::class);
            
            
            foreach ($variables as $variable) {
                $get_column = $query->GetColumnMeta($i);
                
                if ($variable) {
                    //if ($get_column['name'] == array_keys($variables)[$i+1] ){
                    if (in_array($get_column['name'], array_keys($variables), true)) {
                        //
                    } else {
                        // add the columns to the table
                        $column_name = array_keys($variables)[$i+1];
                        $data_type = array_values($variables)[$i+1];
                        $old_column = $get_column['name'];
                        
                        if ($data_type === "varchar") {
                            $data_type = "VARCHAR (255)";
                        } elseif ($data_type === "int") {
                            $data_type = "INT (11)";
                        } elseif ($data_type === "datetime") {
                            $data_type = "DATETIME";
                        } elseif ($data_type === "date") {
                            $data_type = "DATE";
                        } elseif ($data_type === "text") {
                            $data_type = "TEXT(1500)";
                        }
                        
                        // modify table
                        if ($this->db_engine->query("ALTER TABLE $table CHANGE $old_column $column_name $data_type")) {
                                //
                        } else {
                            if ($data_type === "varchar") {
                                $data_type = "VARCHAR (255)";
                            } elseif ($data_type === "int") {
                                $data_type = "INT (11)";
                            } elseif ($data_type === "datetime") {
                                $data_type = "DATETIME";
                            } elseif ($data_type === "date") {
                                $data_type = "DATE";
                            } elseif ($data_type === "text") {
                                $data_type = "TEXT(1500)";
                            }
                            
                            
                            $this->db_engine->query("ALTER TABLE $table ADD $column_name $data_type");
                        }
                        // add column
                    }
                    $i++;
                }
            }
        }
        
        
        if (!$query->rowCount() && $this->create == true) {
            // check if database exists
            global $config;

            $table = static::class;
            $query = $this->db_engine->query("SHOW TABLES LIKE '$table' ");
            
            // Construct a query
            $testing = get_class_vars(static::class);
            $query_s = "";
            $query_s .= "CREATE TABLE $table ( ";
            foreach ($testing as $key => $value) {
                if ($value === "id") {
                    $query_s .= $key ." INT (11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, ";
                } elseif ($value === "int") {
                    $query_s .= $key ." INT (11), ";
                } elseif ($value === "varchar") {
                    $query_s .= $key ." VARCHAR (255) NOT NULL,";
                }
            }
            $query_s = rtrim($query_s, ",");
            $query_s .= ")";
            
            // table does not exist, create the table
            $this->db_engine->query($query_s);
        }

        return static::class;
    }
    
    public function setDatabase($database)
    {
        $this->database = $database;

        global $config;

        $connection = 'mysql:host=localhost;dbname='.$this->database;
        $this->db_engine = new \PDO(
            $connection,
            $config['database']['username'],
            $config['database']['password']
        );
        return true;
    }
    
    public function __call($method, $value)
    {
        //remove set from the variable
        
        $choice = explode("_", $method);
        
        if ($choice[0] == "update") {
            // This is an update call
            $method = explode("update", $method);
            $method = strtolower(substr_replace($method[1], ":", 0, 0));
            $method = str_replace("_", "", $method);
            $this->update[$method] = $value[0];
        } elseif ($choice[0] == "set") {
            // make this elseif and do "set"
            // This is an set call to be inserted into a database.
            
            $method = explode("set", $method);
            $method = strtolower(substr_replace($method[1], ":", 0, 0));
            $method = str_replace("_", "", $method);
            
            $this->arr[$method] = $value[0];
        } else {
            // make this else here

            // find the string contraint leagues_users_fk
            // explode _
            // $cl = new explode[1]();
            // return $cl;

            global $config;

            //echo "<br>SELECT * FROM teams, info WHERE teams.pokemon = info.id<br>";
            $class_name = static::class;
            $query = $this->db_engine->query("
                SELECT `REFERENCED_TABLE_NAME`, `REFERENCED_TABLE_SCHEMA`, `TABLE_SCHEMA` 
                FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` 
                WHERE `TABLE_SCHEMA` = '$this->database' 
                AND `TABLE_NAME` = '$class_name' 
                AND `COLUMN_NAME` = '$method';
                ");
            /*
            echo "SELECT `REFERENCED_TABLE_NAME`, `REFERENCED_TABLE_SCHEMA`, `TABLE_SCHEMA` 
                FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` 
                WHERE `TABLE_SCHEMA` = '$this->database' 
                AND `TABLE_NAME` = '$class_name' 
                AND `COLUMN_NAME` = '$method'";
            */
            $key_table = $query->fetchAll()[0];

            $reference_table = $key_table['REFERENCED_TABLE_NAME'];
            $reference_schema = $key_table['REFERENCED_TABLE_SCHEMA'];
            $table_schema = $key_table['TABLE_SCHEMA'];

            //print_r($reference_schema = $query->fetchAll());
            // get column name "name"
            
            // return single result?

            //endstate
            // $gym->eliteFour1Team(2)
            // get 1
            // returns where teams equals 1
            
            if (!$value) {
                $cl = new $reference_table();
                
                //$cl->getColumnName();
                $sql = "SELECT * FROM $table_schema.$class_name, $reference_schema.$reference_table WHERE $table_schema.$class_name.$method = $reference_schema.$reference_table.id";

                return $cl->query($sql);
            } elseif (is_integer($value)) {
                $fk = $this->filter("id = '$value[0]' ")[0][$method];

                $cl = new $reference_table();
                return $cl->filter("id = '$fk' ")[0];
            } else {
                $cl = new $reference_table();
                //$cl->getColumnName();
                $sql = "SELECT * FROM $class_name, $reference_table WHERE $class_name.$method = $reference_table.id AND $value[0]";

                //echo "SELECT * FROM $class_name, $reference_table WHERE $class_name.$method = $reference_table.id AND $value[0]";
                return $cl->query($sql);
            }
        }
        //return $cl->getColumns();
    }

    public function getColumns()
    {
        global $config;

        $table_name = static::class;
        
        if (strpos(static::class, "_") !== false) {
            if ($this->db_engine->query("SHOW TABLES LIKE '$table_name' ")->num_rows) {
                $table = str_replace("_", "-", static::class);
            } else {
                $table = static::class;
            }
        } else {
            $table = static::class;
        }

        $rs = $this->db_engine->query("SELECT * FROM $table LIMIT 1");
        for ($i = 0; $i < $rs->columnCount(); $i++) {
            $col = $rs->getColumnMeta($i);
            //print_r($col);
            $columns[] = $col['name'];
        }
        return $columns;
    }

    public function getColumnName()
    {
        global $config;

        $table_name = static::class;

        if (strpos(static::class, "_") !== false) {
            if ($this->db_engine->query("SHOW TABLES LIKE '$table_name' ")->num_rows) {
                $table = str_replace("_", "-", static::class);
            } else {
                $table = static::class;
            }
        } else {
            $table = static::class;
        }

        $rs = $this->db_engine->query("SELECT * FROM $table LIMIT 1");
        for ($i = 0; $i < $rs->columnCount(); $i++) {
            $col = $rs->getColumnMeta($i);
            print_r($col);
            $columns[] = $col['name'];
        }
        return $columns;
    }

    public function createInsertQuery()
    {
        foreach (array_keys($this->arr) as $key) {
            $newkey .= " ".$key;
        }
        
        $cols = str_replace(":", "", trim($newkey));
        $cols = str_replace(" ", ",", $cols);
        
        $newkey = str_replace(" ", ",", trim($newkey));

        $table = $this->getTableName();

        return $view_query = "INSERT INTO `$table` ($cols) VALUES($newkey)";
    }

    public function createUpdateQuery($where_clause)
    {
        foreach ($this->update as $key => $value) {
            $values .= "`".$key."` = '".$value."',";
        }
        
        $val = str_replace(":", "", $values);
        $val = rtrim($val, ",");

        $table = $this->getTableName();

        return $sql = "UPDATE `$table` SET $val WHERE $where_clause LIMIT 1";
    }

    public function createInsertQueryFromPost($post)
    {
        foreach ($post as $key => $value) {
            $cols .= "--".$key;
            $values .= "--'".$value."'";
        }

        $cols = str_replace("--", ",", trim($cols));
        $cols = ltrim($cols, ',');
        $values = str_replace("--", ",", trim($values));
        $values = ltrim($values, ",");

        $table_name = $this->getTableName();

        return $view_query = "INSERT INTO `$table` ($cols) VALUES ($values)";
    }

    public function getTableName()
    {
        if (strpos(static::class, "_") !== false) {
            if ($this->db_engine->query("SHOW TABLES LIKE '$table_name' ")->num_rows) {
                return $table = str_replace("_", "-", static::class);
            } else {
               return $table = static::class;
            }
        } else {
            return $table = static::class;
        }
    }
    
    #### CREATE
    
    public function insert()
    {
        $insert_query = $this->createInsertQuery();
        return $query = $this->db_engine->prepare($insert_query);
    }

    public function insertFromPost($post)
    {
        $insert_query = $this->createInsertQueryFromPost($post);
        return $query = $this->db_engine->query($insert_query);
    }

    public function json()
    {
        $this->json = true;
        return $this;
    }

    public function getCall()
    {
        if ($this->json == true) {
            $this->json = false;
            return json_encode($this->last_call);
        } else {
            return $this->last_call;
        }
    }

    public function cache($time)
    {
        $this->cache_time = $time;
        return $this;
    }

    public function seconds()
    {
        $this->cache = true;
        $this->cache_time = "+".$this->cache_time." seconds";
        return $this;
    }

    public function minutes()
    {
        $this->cache = true;
        $this->cache_time = "+".$this->cache_time." minutes";
        return $this;
    }

    public function hours()
    {
        $this->cache = true;
        $this->cache_time = "+".$this->cache_time." hours";
        return $this;
    }

    public function days()
    {
        $this->cache = true;
        $this->cache_time = "+".$this->cache_time." days";
        return $this;
    }

    public function months()
    {
        $this->cache = true;
        $this->cache_time = "+".$this->cache_time." months";
        return $this;
    }

    public function fetchCache($queryString)
    {
        if (file_exists("../cache/".md5($queryString).".cache.php")) {
            //print_r(filectime("../cache/".md5($queryString).".cache.php"));
            $return = file_get_contents("../cache/".md5($queryString).".cache.php");
            $stripTime = preg_match("/^@\d+/", $return, $matches);
            $expire_time = str_replace("@", "", $matches[0]);
            $return = preg_replace("/^@\d+/", "", $return);
            $file_create_time = filectime("../cache/".md5($queryString).".cache.php");
            if ($expire_time < time()) {
                unlink("../cache/".md5($queryString).".cache.php");
                return false;
            } else {
                $json_decode = json_decode($return, true);

                if (!empty($json_decode)) {
                    return $json_decode;
                }
                return unserialize($return);
            }
        }
    }

    public function storeCache()
    {
        if ($this->cache) {
            $time = strtotime($this->cache_time);
            if (!file_exists("../cache/")) {
                mkdir("../cache/");
            }
            if ($this->json) {
                file_put_contents("../cache/".md5($this->last_query).
                ".cache.php", "@".$time.json_encode($this->last_call));
            } else {
                file_put_contents("../cache/".md5($this->last_query).
                ".cache.php", "@".$time.serialize($this->last_call));
            }
        }
        $this->cache = false;
    }
    
    #### READ
    public function all()
    {
        $table = $this->getTableName();

        $queryString = "SELECT * FROM `$table` WHERE 1";

        if ($this->fetchCache($queryString)) {
            return $this->fetchCache($queryString);
        }

        $query = $this->db_engine->query($queryString);

        $this->last_call = $query->fetchAll();
        $this->last_query = $queryString;

        $this->storeCache();
        return $this->getCall();
    }
    
    public function get($where_clause = 1)
    {
        $table = $this->getTableName();

        $queryString = 'SELECT * FROM `'.$table.'` WHERE '.$where_clause.' LIMIT 1';
        
        if ($this->fetchCache($queryString)) {
            return $this->fetchCache($queryString);
        }

        $query = $this->db_engine->query($queryString);

        $this->last_call = $query->fetchAll()[0];
        $this->last_query = $queryString;

        $this->storeCache();

        return $this->getCall();
    }
    
    public function filter($where_clause = null)
    {
        $table = $this->getTableName();
        
        $queryString = 'SELECT * FROM `'.$table.'` WHERE '.$where_clause;
        
        if ($this->fetchCache($queryString)) {
            return $this->fetchCache($queryString);
        }
        
        $query = $this->db_engine->query($queryString);

        $this->last_call = $query->fetchAll();
        $this->last_query = $queryString;

        $this->storeCache();

        return $this->getCall();
    }

    public function toJson($where_clause = 1)
    {
        $table = $this->getTableName();

        $query = $this->db_engine->query("SELECT * FROM $table WHERE $where_clause");

        $this->last_call = $query->fetchAll();
        
        return $this->getCall();
    }

    public function query($sql, $where = '')
    {
        $table = $this->getTableName();

        $query = $this->db_engine->query($sql." $where");

        $this->last_call = $query->fetchAll();
        
        return $this->getCall();
    }
    
    #### UPDATE
    
    public function update($where_clause = 1)
    {
        $sql = $this->createUpdateQuery($where_clause);
        
        return $query = $this->db_engine->query($sql);
    }

    public function updateFromPost($post, $where_clause = 1)
    {
        foreach ($post as $key => $value) {
            $values .= "`".$key."` = '".$value."',";
        }
        
        $val = str_replace(":", "", $values);
        $val = rtrim($val, ",");

        $table = $this->getTableName();
        
        return $query = $this->db_engine->query("UPDATE `$table` SET $val WHERE $where_clause LIMIT 1");
    }
    
    #### DELETE
    
    public function delete($where_clause = 1)
    {
        $this->delete_clause = $where_clause;

        $table = $this->getTableName();
        
        return $query = $this->db_engine->prepare("DELETE FROM `$table` WHERE $where_clause LIMIT 1");
    }
    
    public function deleteAll($where_clause = 1)
    {
        $this->delete_clause = $where_clause;

        $table = $this->getTableName();

        return $query = $this->db_engine->prepare("DELETE FROM `$table` WHERE $where_clause ");
    }
    
    public function confirm()
    {
        try {
            $this->delete($this->delete_clause)->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function confirmAll()
    {
        try {
            $this->deleteAll($this->delete_clause)->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    
    public function save()
    {
        try {
            if (!empty($this->arr)) {
                $this->insert()->execute($this->arr);
            }
            
            if (!empty($this->update)) {
                $this->update()->execute($this->update);
            }
            $this->arr = array();
            $this->update = array();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function works()
    {
        echo "works";
    }
    
    public function viewSave()
    {
        print_r($this->arr);
    }
    
    public function viewUpdate()
    {
        print_r($this->update);
    }
    
    public function viewQuery()
    {
        echo $this->include()->view_query;
    }
}
