<?php

namespace Mira\Models;

$config = require_once $_SERVER['DOCUMENT_ROOT']."/config/config.php";

class Model
{
    public $database = null;
    private $update = false;
    public $structure = array();
    private $db_engine = false;
    
    public function __construct($default_database = null)
    {
        if (isset($this->database)) {
            //
        } elseif ($default_database === null) {
            echo("DID NOT SUPPLY DATABASE INSIDE INSTATIATED CLASS");
        } else {
            $this->database = $default_database;
        }

        $this->database_connected = false;

        $this->information = new \stdClass();

        if (method_exists($this, 'create')) {
            $this->create = true;
            $this->createDatabaseIfNotExists();
            $this->create();
            $this->createTable();
        }

        
        return $this->getTableName();
    }

    public function createTable()
    {
        unset($this->lastrow);
        if ($this->create == true) {
            $table = $this->getTableName();
            $query = $this->db_engine->query("SHOW TABLES LIKE '$table' ");
            if ($query->rowCount()) {
                // table exists, alter columns
                $table_columns = $this->getColumns();
                
                // match the get column ['name'] to each element in the array.
                $create_columns = $this->getClassCreateVariables();

                $i = 0;

                // alter table loop
                foreach ($create_columns as $key => $value) {
                    if ($table_columns[$i]) {
                        $query_string =  "ALTER TABLE $table CHANGE $table_columns[$i] ".$key;
                    } else {
                        $query_string = "ALTER TABLE $table ADD ".$key;
                    }

                    if (preg_match('/^id/', $value)) {
                        $query_string .= " INT (11) UNSIGNED AUTO_INCREMENT PRIMARY KEY ";
                    } elseif (preg_match('/^int/', $value)) {
                        $query_string .= " INT (11)";
                    } elseif (preg_match('/^varchar/', $value)) {
                        $keys = explode(":", $value);
                        if (!$keys[1]) {
                            $keys[1] = 200;
                        }
                        $query_string .= " VARCHAR ($keys[1]) NOT NULL";
                    } elseif (preg_match('/^text/', $value)) {
                        $keys = explode(":", $value);
                        if (!$keys[1]) {
                            $keys[1] = 200;
                        }
                        $query_string .= " TEXT NOT NULL";
                    } elseif (preg_match('/^datetime/', $value)) {
                        $keys = explode(":", $value);
                        $query_string .= " DATETIME NOT NULL";

                        if (isset($keys[1])) {
                            $query_string .= " DEFAULT $keys[1]";
                        }
                    } elseif (preg_match('/^date/', $value)) {
                        $keys = explode(":", $value);
                        $query_string .= " DATE NOT NULL";

                        if ($keys[1]) {
                            if ($key = "CURRENT_TIMESTAMP") {
                                $query_string .= " DEFAULT '".date('Y-m-d')."'";
                            }
                        }
                    } elseif (preg_match('/^fk/', $value)) {
                        $query_string .= $this->updateForeignKey($table_columns[$i], $key, $value);
                    }

                    if (!$this->db_engine->query($query_string)) {
                        if ($this->db_engine->errorInfo()[1] == 1025) {
                            // Cannot drop foreign key constraint
                            if ($table_columns[$i]) {
                                $query_string =  "ALTER TABLE $table CHANGE $table_columns[$i] ".$key;
                            } else {
                                $query_string = "ALTER TABLE $table ADD ".$key;
                            }
                            $query_string .= " INT (11), ADD CONSTRAINT `$key"."_$keys[1]_$keys[2]_fk` FOREIGN KEY ($key) REFERENCES $keys[1]($keys[2])";

                            if ($keys[3]) {
                                $query_string .= " ON DELETE $keys[3]";
                            }

                            if ($keys[4]) {
                                 $query_string .= " ON UPDATE $keys[4]";
                            }
                        }
                        $this->db_engine->query($query_string);
                    }
                    
                    $i++;
                }
            } else {
                // the table does not exist
                // Construct a query
                $object_vars = get_object_vars($this);
                $query_s = "";
                foreach ($object_vars as $key => $value) {
                    if (is_string($value)) {
                        if ($value === "id") {
                            $query_s .= $key ." INT (11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,";
                        } elseif ($value === "int") {
                            $query_s .= $key ." INT (11),";
                        } elseif (preg_match('/^varchar/', $value)) {
                            $keys = explode(":", $value);
                            if (!$keys[1]) {
                                $keys[1] = 200;
                            }
                            $query_s .= $key ." VARCHAR ($keys[1]) NOT NULL,";
                        } elseif (preg_match('/^datetime/', $value)) {
                            $keys = explode(":", $value);
                            $query_s .= $key." DATETIME NOT NULL";

                            if ($keys[1]) {
                                $query_s .= " DEFAULT $keys[1]";
                            }
                        } elseif (preg_match('/^date/', $value)) {
                            $keys = explode(":", $value);
                            $query_s .= $key." DATE NOT NULL";

                            if ($keys[1]) {
                                if ($key = "CURRENT_TIMESTAMP") {
                                    $query_s .= " DEFAULT '".date('Y-m-d')."'";
                                }
                            }
                        }
                    }
                }
                $query_s = rtrim($query_s, ",");
                $query = "CREATE TABLE $table ( ".$query_s . ") ENGINE=InnoDB;";

                // table does not exist, create the table
                $this->db_engine->query($query);

                
                $this->addForeignKey($object_vars);
            }
        }
    }

    private function updateForeignKey($tablecolumn, $key, $value)
    {
        $keys = explode(':', $value);
        $table = $this->getTableName();
        $create_columns = $this->getClassCreateVariables();
        $drop_foreign_key = "
            ALTER TABLE $table DROP 
            FOREIGN KEY `$keys[1]"."_"."$keys[2]_fk`";

        if ($tablecolumn) {
            $alter_column = "
                ALTER TABLE $table
                CHANGE $tablecolumn $key INT (11) UNSIGNED";
        } else {
            $alter_column = "
                ALTER TABLE $table
                ADD $key INT (11) UNSIGNED";
        }

        $add_foreign_key_constraint = "
            ALTER TABLE $table 
            ADD CONSTRAINT `$keys[1]_$keys[2]_fk` 
            FOREIGN KEY ($key) REFERENCES $keys[1]($keys[2])";

        if ($keys[3]) {
            $add_foreign_key_constraint .= " ON DELETE $keys[3]";
        }

        if ($keys[4]) {
             $add_foreign_key_constraint .= " ON UPDATE $keys[4]";
        }

        $this->db_engine->query($drop_foreign_key);
        $this->db_engine->query($alter_column);
        $this->db_engine->query($add_foreign_key_constraint);
    }

    private function addForeignKey($object_vars)
    {
        foreach ($object_vars as $key => $value) {
            if (is_string($value) && preg_match('/^fk/', $value)) {
                $keys = explode(':', $value);
                $table = $this->getTableName();
                $add_foreign_key_field = "ALTER TABLE $table ADD ".$key." INT (11) UNSIGNED";
                $add_foreign_key_constraint = "ALTER TABLE $table ADD CONSTRAINT `$keys[1]_$keys[2]_fk` FOREIGN KEY ($key) REFERENCES $keys[1]($keys[2])";

                if ($keys[3]) {
                    $add_foreign_key_constraint .= " ON DELETE $keys[3]";
                }

                if ($keys[4]) {
                    $add_foreign_key_constraint .= " ON UPDATE $keys[4]";
                }
                $this->db_engine->query(
                    $add_foreign_key_field
                );

                $this->db_engine->query(
                    $add_foreign_key_constraint
                );
            }
        }
        
        return $query_string;
    }

    private function getClassCreateVariables()
    {
        $allClassProperties = get_object_vars($this);
        $defaultClassProperties = get_class_vars(static::class);

        // remove the PDO object from the array
        unset($allClassProperties['db_engine']);
        unset($allClassProperties['create']);
        unset($allClassProperties['lastrow']);
        unset($allClassProperties['update']);
        unset($allClassProperties['last_query']);
        unset($allClassProperties['structure']);
        unset($defaultClassProperties['structure']);
        unset($allClassProperties['information']);

        return array_diff($allClassProperties, $defaultClassProperties);
    }

    public function createDatabaseIfNotExists()
    {
        $this->database_connected = false;
        global $config;
        $connection = 'mysql:host='.$config['database']['host'].';';
        $this->db_engine = new \PDO(
            $connection,
            $config['database']['username'],
            $config['database']['password']
        );
    
        $this->db_engine->query("CREATE DATABASE IF NOT EXISTS ".$this->database);

        $this->makeDatabaseConnection();
        return true;
    }

    public function makeDatabaseConnection()
    {
        if (!$this->database_connected) {
            global $config;
            $connection = 'mysql:host='.$config['database']['host'].';dbname='.$this->database;
            $this->db_engine = new \PDO(
                $connection,
                $config['database']['username'],
                $config['database']['password']
            );
        }
    }

    public function column($rowName)
    {
        $this->$rowName = 'lastrow';
        $this->lastrow = $rowName;
        return $this;
    }

    public function varchar()
    {
        $lastrow = $this->lastrow;
        $this->$lastrow = "varchar";
        return $this;
    }

    public function length($length)
    {
        $lastrow = $this->lastrow;
        $this->$lastrow .= ":".$length;
        return $this;
    }

    public function primaryKey()
    {
        $lastrow = $this->lastrow;
        $this->$lastrow = "id";
        return $this;
    }

    public function int()
    {
        $lastrow = $this->lastrow;
        $this->$lastrow = "int";
        return $this;
    }

    public function foreignKey($referenceTable, $fk = 'id')
    {
        $referenceTable = new $referenceTable();
        $referenceTable->getTableName();

        $lastrow = $this->lastrow;
        $this->$lastrow = "fk".":".$referenceTable->database.".".$referenceTable->getTableName().":".$fk;
        return $this;
    }

    public function dateTime()
    {
        $lastrow = $this->lastrow;
        $this->$lastrow = "datetime";
        return $this;
    }

    public function date()
    {
        $lastrow = $this->lastrow;
        $this->$lastrow = "date";
        return $this;
    }

    public function text()
    {
        $lastrow = $this->lastrow;
        $this->$lastrow = "text";
        return $this;
    }

    public function default($default_value)
    {
        $lastrow = $this->lastrow;
        if ($default_value == 'now') {
            $this->$lastrow .= ":CURRENT_TIMESTAMP";
        }
        return $this;
    }

    public function onDelete($option)
    {
        $lastrow = $this->lastrow;
        $this->$lastrow .= ":".$option;
        return $this;
    }

    public function onUpdate($option)
    {
        $lastrow = $this->lastrow;
        $this->$lastrow .= ":".$option;
        return $this;
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
        $this->makeDatabaseConnection();
        
        $choice = explode("_", $method);

        $class_name = $this->getTableName();
        $query = $this->db_engine->query("
            SELECT `REFERENCED_TABLE_NAME`, `REFERENCED_TABLE_SCHEMA`, `TABLE_SCHEMA` 
            FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` 
            WHERE `TABLE_SCHEMA` = '$this->database' 
            AND `TABLE_NAME` = '$class_name' 
            AND `COLUMN_NAME` = '$method';
        ");
        
        $key_table = $query->fetchAll()[0];

        $reference_table = $key_table['REFERENCED_TABLE_NAME'];
        $reference_schema = $key_table['REFERENCED_TABLE_SCHEMA'];
        $table_schema = $key_table['TABLE_SCHEMA'];

        if (!$value) {
            $reflection = new \ReflectionClass(static::class);
            $foreign_class = $reflection->getNamespaceName()."\\".$reference_table;
            $cl = new $foreign_class();
            
            $sql = "SELECT * FROM $table_schema.$class_name, $reference_schema.$reference_table WHERE $table_schema.$class_name.$method = $reference_schema.$reference_table.id";

            if (isset($this->$method)) {
                return $cl->find($this->$method);
            }
            echo $method;
            return $cl;
        } elseif (is_integer($value[0])) {

            $reflection = new \ReflectionClass(static::class);
            $foreign_class = $reflection->getNamespaceName()."\\".$reference_table;
            $cl = new $foreign_class();
            return $cl->find($value[0]);
        } else {

            $cl = new $reference_table();
            $sql = "SELECT * FROM $class_name, $reference_table WHERE $class_name.$method = $reference_table.id AND $value[0]";
            return $cl->query($sql);
        }
    
        //return $cl->getColumns();
    }

    public function getColumns()
    {
        $table_name = $this->getTableName();
        
        if (strpos($table_name, "_") !== false) {
            if ($this->db_engine->query("SHOW TABLES LIKE '$table_name' ")->num_rows) {
                $table = str_replace("_", "-", $table_name);
            } else {
                $table = $this->getTableName();
            }
        } else {
            $table = $this->getTableName();
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

        $table_name = $this->getTableName();

        $rs = $this->db_engine->query("SELECT * FROM $table LIMIT 1");
        for ($ei = 0; $i < $rs->columnCount(); $i++) {
            $col = $rs->getColumnMeta($i);
            $columns[] = $col['name'];
        }
        return $columns;
    }

    public function createInsertQuery()
    {
        $this->makeDatabaseConnection();
        foreach ($this->getClassCreateVariables() as $key => $value) {
            
            $newkey .= " :".$key;
        }
        
        $cols = str_replace(":", "", trim($newkey));
        $cols = str_replace(" ", ",", $cols);
        
        $newkey = str_replace(" ", ",", trim($newkey));

        $table = $this->getTableName();
        return $view_query = "INSERT INTO `$table` ($cols) VALUES($newkey)";
    }

    public function createUpdateQuery()
    {
        $this->makeDatabaseConnection();
        $id = $this->id;
        unset($this->id);
        $values = "";
        foreach ($this->getClassCreateVariables() as $key => $value) {
            $values .= " `".$key."` = :".$key.",";
        }
        
        $val =  $values;
        $val = rtrim($val, ",");

        $table = $this->getTableName();

        $where_clause = "id = '$id' ";
        $sql = "UPDATE `$table` SET $val WHERE $where_clause LIMIT 1";
        $prepared_query = $this->db_engine->prepare($sql);
        
        foreach ($this->getClassCreateVariables() as $key => $value) {
            $prepared_query->bindValue($key, $value);
        }
        return $prepared_query;
    }

    public function createUpdateQueryFromPost($post)
    {
        $this->makeDatabaseConnection();
        $val = "";
        foreach ($post as $key => $value) {
            $val .= "`".$key."` = :".$key.",";
        }
        
        $val = rtrim($val, ",");

        $table = $this->getTableName();

        if (isset($post['id'])) {
            $where_clause = "id = :id";
        } 

        $sql = "UPDATE `$table` SET $val WHERE $where_clause LIMIT 1";

        $prepared_query = $this->db_engine->prepare($sql);

        foreach ($post as $key => $value) {
            $prepared_query->bindValue($key, $value);
        }

        return $prepared_query;
    }

    public function createInsertQueryFromPost($post)
    {
        $this->makeDatabaseConnection();
        foreach ($post as $key => $value) {
            $cols .= "--".$key;
            $values .= ":".$key.",";
        }

        $cols = str_replace("--", ",", trim($cols));
        $cols = ltrim($cols, ',');
        //$values = str_replace("--", ",", trim($values));
        $values = rtrim($values, ",");

        $table_name = $this->getTableName();

        $view_query = "INSERT INTO `$table_name` ($cols) VALUES ($values)";

        $prepared_query = $this->db_engine->prepare($view_query);

        foreach ($post as $key => $value) {
            $prepared_query->bindValue($key, $value);
        }

        return $prepared_query;
    }

    public function getTableName()
    {
        $this->makeDatabaseConnection();
        if (strpos(static::class, "_") !== false) {
            $table_name = strtolower(end(explode("\\", static::class)));
            if ($this->db_engine->query("SHOW TABLES LIKE '$table_name' ")->num_rows) {
                return str_replace("_", "-", strtolower(end(explode("\\", static::class))));
            } else {
                return strtolower(end(explode("\\", static::class)));
            }
        } else {
            $explode_namespace = explode("\\", static::class);
            return strtolower(end($explode_namespace));
        }
    }
    
    #### CREATE
    
    public function insert()
    {
        $this->triggerEvent('creating');
        $insert_query = $this->createInsertQuery();
        $prepared_query = $this->db_engine->prepare($insert_query);

        foreach ($this->getClassCreateVariables() as $key => $value) {
            $prepared_query->bindParam($key, $value);
        }

        return $prepared_query;
    }

    public function exclude()
    {
        foreach(func_get_args() as $key) {
            unset($_POST[$key]);
        }
        return $this;
    }

    public function insertFromPost()
    {
        $this->makeDatabaseConnection();
        $this->triggerEvent('creating');
        $insert_query = $this->createInsertQueryFromPost($_POST);
        $insert_query->execute();
        $this->information->lastInsertId = $this->db_engine->lastInsertId();
        $this->triggerEvent('created');
        return $this;
    }

    public function json()
    {
        $this->json = true;
        return $this;
    }

    public function getCall()
    {
        $this->triggerEvent('fetched');
        if ($this->json == true) {
            $this->json = false;
            return json_encode($this->last_call);
        } else {
            return json_decode(json_encode($this->last_call), false);
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

                if ($this->json) {
                    return json_encode(unserialize($return));
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

    private function callEvent($event, $trigger)
    {
        $pos = strrpos($event, "\\");

        if ($pos !== false) {
            $subject = $event."::".$trigger;
        }
        $new_instance = $this;
        unset($new_instance->Events);
        return $subject($new_instance);
    }

    public function triggerEvent($event)
    {
        if (isset($this->Events)) {
            foreach($this->Events as $key => $value){
                if ($key == $event) {
                    $this->callEvent($this->Events[$event], $key);
                    return;
                }
            }
        }
    }
    
    #### READ
    public function all()
    {
        $this->triggerEvent('fetching');
        $table = $this->getTableName();

        $queryString = "SELECT * FROM `$table` WHERE 1";

        if ($this->fetchCache($queryString)) {
            return $this->fetchCache($queryString);
        }

        $this->makeDatabaseConnection();

        $query = $this->db_engine->query($queryString);

        $this->last_call = $query->fetchAll();
        $this->last_query = $queryString;

        $this->storeCache();
        return $this->getCall();
    }
    
    public function get($where_clause = 1)
    {
        $this->triggerEvent('fetching');
        $table = $this->getTableName();

        $queryString = 'SELECT * FROM `'.$table.'` WHERE '.$where_clause.' LIMIT 1';
        
        if ($this->fetchCache($queryString)) {
            return $this->fetchCache($queryString);
        }

        $this->makeDatabaseConnection();

        $query = $this->db_engine->query($queryString);

        $this->last_call = $query->fetchAll()[0];
        $this->last_query = $queryString;

        $this->storeCache();

        return $this->getCall();
    }

    public function find($value)
    {
        if (is_numeric($value)) {
            $result = $this->get("id = '$value'");
        } else {
            $result = $this->get($value);
        }
        
        $new_instance = new $this;

        $new_instance->update = true;
        if (count($result)) {
            foreach ($result as $key => $value) {
                
                if (!is_numeric($key)) {
                    $new_instance->$key = $value;
                }
            }
            
            unset ($new_instance->last_query);
            return $new_instance;
        }


        return null;
    }
    
    public function filter($where_clause = null)
    {
        $this->triggerEvent('fetching');
        $table = $this->getTableName();
        
        $queryString = 'SELECT * FROM `'.$table.'` WHERE '.$where_clause;
        
        if ($this->fetchCache($queryString)) {
            return $this->fetchCache($queryString);
        }

        $this->makeDatabaseConnection();
        
        $query = $this->db_engine->query($queryString);

        $this->last_call = $query->fetchAll();
        $this->last_query = $queryString;

        $this->storeCache();

        return $this->getCall();
    }

    public function toJson($where_clause = 1)
    {
        $this->makeDatabaseConnection();
        $table = $this->getTableName();

        $query = $this->db_engine->query("SELECT * FROM $table WHERE $where_clause");

        $this->last_call = $query->fetchAll();
        
        return $this->getCall();
    }

    public function query($sql, $where = '')
    {
        $this->makeDatabaseConnection();
        $table = $this->getTableName();

        $query = $this->db_engine->query($sql." $where");

        $this->last_call = $query->fetchAll();
        
        return $this->getCall();
    }
    
    #### UPDATE
    
    public function update($where_clause = 1)
    {
        $this->triggerEvent('updating');
        $this->makeDatabaseConnection();
        $query = $this->createUpdateQuery();
        
        $this->triggerEvent('updated');
        $this->update = false;
        return $query;
    }

    public function updateFromPost()
    {
        $this->triggerEvent('updating');
        $this->makeDatabaseConnection();
        $query = $this->createUpdateQueryFromPost($_POST);
        $query->execute();
        return $query;
    }
    
    #### DELETE
    
    public function delete($where_clause = 1)
    {
        $this->makeDatabaseConnection();
        $this->delete_clause = $where_clause;

        $table = $this->getTableName();
        
        return $query = $this->db_engine->prepare("DELETE FROM `$table` WHERE $where_clause LIMIT 1");
    }
    
    public function deleteAll($where_clause = 1)
    {
        $this->makeDatabaseConnection();
        $this->delete_clause = $where_clause;

        $table = $this->getTableName();

        return $query = $this->db_engine->prepare("DELETE FROM `$table` WHERE $where_clause ");
    }
    
    public function confirm()
    {
        try {
            $this->triggerEvent('deleting');
            $this->delete($this->delete_clause)->execute();
            $this->triggerEvent('deleted');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function confirmAll()
    {
        try {
            $this->triggerEvent('deleting');
            $this->deleteAll($this->delete_clause)->execute();
            $this->triggerEvent('deleted');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    
    public function save()
    {
        try {
            $this->triggerEvent('saving');
            if (!$this->update) {
                
                if (!empty($this->getClassCreateVariables())) {
                    $this->triggerEvent('creating');
                    $this->insert()->execute($this->getClassCreateVariables());
                    $this->triggerEvent('created');
                    return true;
                }
            }
            
            $this->triggerEvent('updating');
            $this->update()->execute();
            $this->triggerEvent('updated');

            $this->triggerEvent('saved');
            
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
