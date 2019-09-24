<?php

// For using the PHP MongoDB Library.
require_once 'vendor/autoload.php';

// For logging exceptions to file
require_once 'logException.php';

// Variables for database connection.
$database_name = "test_TestAssets";
$database_url = "localhost:27017";
$database_user = "tester";    
$database_password = "tester123";

// Connect to MongoDB Server.
if ($database_user == "" && $database_password == "") {
    $client = new \MongoDB\Client("mongodb://${database_url}/${database_name}");
}
else {
    $client = new \MongoDB\Client("mongodb://${database_user}:${database_password}@${database_url}/${database_name}");                    
}

// Exception handling to check whether successful connection
// to database has been established and the user has been authenticated.
try {
    $client->listDatabases();
}
catch(MongoDB\Driver\Exception\Exception $catchedException){
    logException(get_class($catchedException)." : ".$catchedException->getMessage()); 
}

// Choosing the database
$db = $client->$database_name;

?>