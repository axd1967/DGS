<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require_once 'include/std_functions.php';

connect2mysql();

disable_cache();

$result = mysql_query("select ID as gid,Black_ID,White_ID from Games " .
      "where Status != 'INVITED' AND Status != 'FINISHED'  order by ID");

while ( $row = mysql_fetch_array($result) )
{
   extract($row);
   echo $gid .' ';
   $query = "select Stone from Moves where gid=$gid and (Stone=1 or Stone=2) order by ID desc limit 1";

   $res = mysql_query( $query ) or die(mysql_error() . '<p>' . $query);

   if ( mysql_num_rows($res) != 1 )
      $Stone = 2;
   else
      extract(mysql_fetch_array($res));

   $tomove_ID = ( $Stone==1 ? $White_ID : $Black_ID );

   $query = "update Games set ToMove_ID=$tomove_ID WHERE ID=$gid LIMIT 1";

   mysql_query( $query ) or die(mysql_error() . '<p>' . $query);
}

echo "Done.";

?>
