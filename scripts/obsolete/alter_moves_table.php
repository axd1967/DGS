<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );

connect2mysql();

disable_cache();

$result = mysql_query("select ID as gid from Games where Status!='INVITED' AND ID>514 order by ID")
     or die("Error: " . mysql_error() );
while( $row = mysql_fetch_array($result) )
{
  extract($row);
  echo $gid . ' ';
  mysql_query("alter table Moves$gid modify ID smallint unsigned not null auto_increment")
    or die("Error: " . mysql_error() );
  mysql_query("alter table Moves$gid modify PosX smallint")
    or die("Error: " . mysql_error() );
  mysql_query("alter table Moves$gid modify PosY smallint")
    or die("Error: " . mysql_error() );
  mysql_query("alter table Moves$gid modify Stone smallint unsigned not null default 0")
    or die("Error: " . mysql_error() );
  mysql_query("alter table Moves$gid modify MoveNr smallint unsigned")
    or die("Error: " . mysql_error() );
  mysql_query("alter table Moves$gid add Hours smallint unsigned after PosY")
    or die("Error: " . mysql_error() );
}

?>
