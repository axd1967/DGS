<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require_once 'include/std_functions.php';

connect2mysql();

disable_cache();

$result = mysql_query("select ID as gid from Games where Status != 'INVITED' AND Moves>0 order by ID");

while ( $row = mysql_fetch_array($result) )
{
   extract($row);
   echo $gid .' ';
   $query = "select PosX,PosY from Moves where gid=$gid order by ID desc LIMIT 1";

   $res = mysql_query( $query ) or die(mysql_error() . '<p>' . $query);

   if ( mysql_num_rows($res) != 1 )
      $query = "update Games set Status='INVITED' WHERE ID=$gid LIMIT 1";
   else
   {
      extract(mysql_fetch_array($res));
      $query = "update Games set Last_X=$PosX,Last_Y=$PosY WHERE ID=$gid LIMIT 1";
   }
   mysql_query( $query ) or die(mysql_error() . '<p>' . $query);
}

echo "Done.";

?>
