<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );

connect2mysql();

disable_cache();

$result = mysql_query("select ID as gid from Games where Status!='INVITED'");

while( $row = mysql_fetch_array($result) )
{
   extract($row);
   if( strlen($gid) < 1 )
      die("A: " . mysql_error());

   mysql_query("DROP TABLE Moves$gid")
      or die("B: " . mysql_error());
}

?>
