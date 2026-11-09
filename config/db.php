<?php
  define('BASE_URL', '/patient_portal/');
  
  try{
    $connection = new PDO(
      "mysql:host=localhost;dbname=patient_portal;charset=utf8mb4",
      "root",
      "nmesoma",
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT         => true,
      ]
    );
  }

  catch(PDOException $e){
        echo " error connecting to database <br>";
        //echo $e->getMessage();
  }

 


 


?>