<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );
require( "include/rating.php" );

{
   connect2mysql();


   $result = mysql_query("Select ID,Black_ID, White_ID, Starttime " .
                         "FROM Games WHERE Status!='INVITED'")
      or die(mysql_error());

   echo '<pre>';
   while( $row = mysql_fetch_array($result) )
   {
      $br = get_rating_at($row['Black_ID'], $row['Starttime']);
      if( !is_numeric($br) ) $br = 'NULL';

      $wr = get_rating_at($row['White_ID'], $row['Starttime']);
      if( !is_numeric($wr) ) $wr = 'NULL';

      $query = "UPDATE Games SET Black_Start_Rating=$br, White_Start_Rating=$wr " .
         "WHERE ID=" . $row['ID'] . " LIMIT 1";

      mysql_query( $query )
         or die(mysql_error());

      echo "Game {$row['ID']}  {$row['Starttime']}:    B: {$row['Black_ID']}  $br   W: {$row['White_ID']}  $wr\n";
   }

   echo '</pre><p>FINISHED';
}
?>
