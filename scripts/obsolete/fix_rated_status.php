<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );
require( "include/rating.php" );

{
   connect2mysql();


   $result = mysql_query(
      "select Players.ID as uid from Players,Games where (Games.Black_ID=Players.ID OR Games.White_ID=Players.ID ) AND ( RatingStatus='INIT' or RatingStatus='READY' ) AND Games.Status!='INVITED' AND Games.Rated!='N' Group by Players.ID" )
      or die(mysql_error());

   $query = "UPDATE Players SET RatingStatus='RATED' WHERE ID IN (0";

   while( $row = mysql_fetch_array($result) )
   {
      $query .= ", " . $row['uid'];
   }

   $query .= ')';

   mysql_query( $query )
      or die(mysql_error());

   mysql_query( "UPDATE Players SET RatingStatus='INIT' WHERE RatingStatus='READY'" )
      or die( mysql_error());

   echo '<p>FINISHED';
}
?>
