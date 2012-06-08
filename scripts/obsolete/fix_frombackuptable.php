<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );

connect2mysql();

disable_cache();

$result = mysql_query("select ID as gid from Games " .
		"where Status != 'INVITED'  order by ID");

  while( $row = mysql_fetch_array($result) )	
{
	extract($row);
	echo $gid .' ';
   	$query = "select Lastchanged,White_Maintime,White_Byotime,White_Byoperiods " .
		"from Games2 where ID=$gid " .
		"order by ID desc limit 1";

   	$res = mysql_query( $query ) 
		or die(mysql_error() . '<p>' . $query);

	if( mysql_num_rows($res) != 1 )
	{
		$query = "update Games set Lastchanged=Starttime, ".
		"White_Maintime=Maintime, White_Byotime=0, White_Byoperiods=-1, " .
		"Black_Maintime=Maintime, Black_Byotime=0, Black_Byoperiods=-1 " .
		"WHERE ID=$gid LIMIT 1";
	}
	else
	{
		extract(mysql_fetch_array($res));
		if( $White_Maintime == NULL ) $White_Maintime = 'NULL';
		$query = "update Games set Lastchanged='$Lastchanged', ".
		"White_Maintime=$White_Maintime, White_Byotime=$White_Byotime, ".
		"White_Byoperiods=$White_Byoperiods " .
		"WHERE ID=$gid LIMIT 1";

	}

  	mysql_query( $query )
      		or die(mysql_error() . '<p>' . $query);
   }

   echo "Done.";
?>
