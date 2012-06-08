<?php

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );
require( "include/rating.php" );

{
   connect2mysql();


   $result = mysql_query("Select * from FAQ where Level>0")
      or die(mysql_error());

   echo '<pre>';
   while( $row = mysql_fetch_array($result) )
   {
      mysql_query( "INSERT INTO TranslationTexts SET Text='" . mysql_escape_string($row['Question_text']) . "', " .
                   "Ref_ID=" . $row['ID'] . ", Translatable = 'N' " )
         or die(mysql_error());

      $q_id =  mysql_insert_id();
      $a_id = 'NULL';
      mysql_query("INSERT INTO TranslationFoundInGroup SET Text_ID=$q_id, Group_ID=9" )
         or die(mysql_error());
      if( $row['Level'] != 1 )
      {
         mysql_query( "INSERT INTO TranslationTexts SET Text='" . mysql_escape_string($row['Answer_text']) . "', " .
                      "Ref_ID=" . $row['ID'] . ", Translatable = 'N' " )
            or die(mysql_error());

         $a_id =  mysql_insert_id();
         mysql_query("INSERT INTO TranslationFoundInGroup SET Text_ID=$a_id, Group_ID=9" )
            or die(mysql_error());
      }

      mysql_query( "UPDATE FAQ SET Answer=$a_id, Question=$q_id WHERE ID=" . $row['ID'] )
         or die(mysql_error());

   }

   echo '</pre><p>FINISHED';
}
?>
