<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );

connect2mysql();

disable_cache();

$result = mysql_query("SELECT Players.ID,count(*) AS Running FROM Games,Players " .
                      "WHERE Status!='INVITED' AND Status!='FINISHED' " .
                      "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
                      "GROUP BY Players.ID")
      or die("A: " . mysql_error());

while( $row = mysql_fetch_array($result) )
{
   mysql_query("UPDATE Players SET Running=" . $row["Running"] . " WHERE ID=" . $row["ID"])
      or die("B: " . mysql_error());
}

echo "Running Done.<br>";



$result = mysql_query("SELECT Players.ID,count(*) AS Finished FROM Games,Players " .
                      "WHERE Status='FINISHED' " .
                      "AND (Players.ID=White_ID OR Players.ID=Black_ID) " .
                      "GROUP BY Players.ID")
      or die("C: " . mysql_error());

while( $row = mysql_fetch_array($result) )
{
   mysql_query("UPDATE Players SET Finished=" . $row["Finished"] . " WHERE ID=" . $row["ID"])
      or die("D: " . mysql_error());
}

echo "Finished Done.<br>";



$result = mysql_query("SELECT Players.ID,count(*) AS Won FROM Games,Players " .
                      "WHERE Status='FINISHED' " .
                      "AND ((Black_ID=Players.ID AND Score<0) " .
                      "OR (White_ID=Players.ID AND Score>0)) " .
                      "GROUP BY Players.ID")
      or die("E: " . mysql_error());

while( $row = mysql_fetch_array($result) )
{
   mysql_query("UPDATE Players SET Won=" . $row["Won"] . " WHERE ID=" . $row["ID"])
      or die("F: " . mysql_error());
}

echo "Won Done.<br>";




$result = mysql_query("SELECT Players.ID,count(*) AS Lost FROM Games,Players " .
                      "WHERE Status='FINISHED' " .
                      "AND ((Black_ID=Players.ID AND Score>0) " .
                      "OR (White_ID=Players.ID AND Score<0)) " .
                      "GROUP BY Players.ID")
      or die("G: " . mysql_error());

while( $row = mysql_fetch_array($result) )
{
   mysql_query("UPDATE Players SET Lost=" . $row["Lost"] . " WHERE ID=" . $row["ID"])
      or die("H: " . mysql_error());
}

echo "Lost Done.<br>";



?>
