<?php
require_once('ConnectToDb.php');

// Get the data sent by client
$url = $_POST['url'];
$result = $_POST['result'];

// Choose the required collection.
//'$db' is the variable for the choosen database and is defined in the file 'ConnectToDb.php'
$db_collection = $db->test_testVectors;

// Save validation result to database
try{
    $command = $db_collection->updateOne(['url'=>$url],['$set'=>['result'=>$result]]);
}
catch (MongoDB\Driver\Exception\Exception $catchedException){
    logException(get_class($catchedException)." : ".$catchedException->getMessage()); 
}
?>