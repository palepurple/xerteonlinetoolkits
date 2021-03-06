<?php

require_once(dirname(__FILE__) . "/error_library.php");
/** 	
 * 
 * Database library, code for connecting to the database
 *
 * @author Patrick Lockley
 * @version 1.0
 * @copyright Copyright (c) 2008,2009 University of Nottingham
 * @package
 */
// horrible includes.
if (function_exists('database_connect')) {
    return;
}

/**
 * Create PDO connection to the database.
 * @return PDO instance
 * @throws PDOException if it's not setup / working etc.
 */
function database_connect() 
{
    global $xerte_toolkits_site;
    /*
     * Try to connect
     */

    $dsn = false;

    if($xerte_toolkits_site->database_type == 'sqlite') {
        $dsn = "sqlite:{$xerte_toolkits_site->database_location}";
        /* not relevant parameters */
        $xerte_toolkits_site->database_username = null;
        $xerte_toolkits_site->database_password = null;
    }

    if($dsn == false) {
        // default to MySQL.
        $dsn = "mysql:dbname={$xerte_toolkits_site->database_name};host={$xerte_toolkits_site->database_host}";
    }

    $options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

    $db_connection = new PDO($dsn, $xerte_toolkits_site->database_username, $xerte_toolkits_site->database_password, $options); 
//    $db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $db_connection;
}

/**
 * @return boolean true if it looks like the database will work.
 */
function database_is_setup($xerte_toolkits_site) {

    /* sqlite will create a database on trying to connect if it doesn't exist and it can ... hence file_exists() */
    if($xerte_toolkits_site->database_type == 'sqlite') {
        return file_exists($xerte_toolkits_site->database_location);
    } 

    $connection = false;
    try {
        $connection = database_connect();
        return true;
    }
    catch(PDOException $e) {
        _debug("Failed to check if db is working : {$e->getMessage()}");
    }
    return false;
}

/**
 * Execute an SQL query.
 * If it's a select query, then we return a list of all results found (array, string keys).
 * If it's an update or delete query, we return a count of how many rows were changed.
 * If it's an insert query we return the auto-increment id (if appropriate) (lastInsertId()).
 * If the query fails, we should return boolean false.
 *
 * @param string $sql e.g. "SELECT * FROM users WHERE name = ? OR name = ?" or one with named placeholders (... WHERE name = :name OR blah = :fish ...)
 * @param array $params (optional) e.g. array('bob', "david's") or you can use named parameters like array('name' => 'blah', 'job' => 'fish');
 * @return array|boolean false|int 
 */
function db_query($sql, $params = array())
{
    $connection = database_connect();

    _debug("Running : $sql", 1);

    $statement = $connection->prepare($sql);

    $ok = $statement->execute($params);
    if ($ok === false) {
        _debug("Failed to execute query : $sql : " . print_r($connection->errorInfo(), true));
        return false;
    }

    if(preg_match('/^select/i', $sql)) { 
        $rows = array();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    if(preg_match('/^(update|delete)/i', $sql)) {
        return $statement->rowCount();  /* number of rows affected */
    }

    if(preg_match('/^insert/i', $sql)) {
        return $connection->lastInsertId();
    }
    return $ok;
}

/**
 * Convienance query for db_query - retrieve one row
 * @param string $sql
 * @param array $params (optional)
 * @return array (db row) or null
 */
function db_query_one($sql, $params = array())
{
    $results = db_query($sql, $params);

    if (sizeof($results) > 0) {
        return $results[0];
    }
    return null;
}
