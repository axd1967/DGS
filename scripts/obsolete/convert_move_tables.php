<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );

connect2mysql();

disable_cache();

$result = mysql_query("select ID as gid from Games where Status!='INVITED'");

while( $row = mysql_fetch_array($result) )
{
   extract($row);
   mysql_query("INSERT INTO Moves (gid,MoveNr,Stone,PosX,PosY,Hours) " . 
               "SELECT $gid,MoveNr,Stone,PosX,PosY,Hours FROM Moves$gid") 
      or die("A: " . mysql_error());
   mysql_query("INSERT INTO MoveMessages (gid,MoveNr,Text) " .
               "SELECT $gid,MoveNr,Text FROM Moves$gid where Text IS NOT NULL") 
      or die("B: " . mysql_error());

}

?>
