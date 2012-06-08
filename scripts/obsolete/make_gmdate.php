<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );

connect2mysql();

putenv('TZ='. 'CET');

$result = mysql_query("select id,datestamp,UNIX_TIMESTAMP(datestamp) as stamp from dragondisc");

while( $row = mysql_fetch_array( $result ) )
{
  echo $row["ID"] . " " . $row["datestamp"] . '<p>';

  echo  date('Y-m-d H:i:s', $row["stamp"])  . '<p>';
  echo  gmdate('Y-m-d H:i:s', $row["stamp"])  . '<p>' . '<hr>';

}
