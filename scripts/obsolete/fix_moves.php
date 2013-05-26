<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require_once 'include/std_functions.php';

connect2mysql();

disable_cache();

//$result = mysql_query("select ID as gid from Games where Status != 'INVITED' order by ID");



   $query = "select gid,Max(MoveNr) AS Moves from Moves group by gid";
   $result = mysql_query( $query ) 
	or die(mysql_error() . '<p>' . $query);
	
   while( $row = mysql_fetch_array($result) )	
   {
	extract( $row);	
   	if( !($Moves>0) )
       		$Moves = 0;
   	$query = "update Games set Moves=$Moves WHERE ID=$gid LIMIT 1";
  	mysql_query( $query )
      		or die(mysql_error() . '<p>' . $query);
   }

   echo "Done.";
?>
