<?php
require_once("../forum/forum_functions.php");
{
   connect2mysql();

   $res0 = mysql_query( "SELECT * from forums" ) or die(mysql_error());
   while( $row0 = mysql_fetch_array( $res0 ) )
   {
      $fname = $row0['table_name'];

      $r = mysql_single_fetch("SELECT MAX(SortOrder) FROM Forums", 'row' );
      $SortOrder = $r[0] + 1;

      mysql_query("INSERT INTO Forums SET " .
                  "Name=\"" . $row0["name"] . "\", " .
                  "Description=\"" . $row0['description'] . "\", " .
                  "Moderated=" . ($row0["moderation"] == 'y' ? '"Y"' : '"N"') . ", " .
                  "SortOrder=" . ($SortOrder+1)) or die(mysql_error());

      if( mysql_affected_rows() != 1)
         die(mysql_error());

      $new_forum_ID = mysql_insert_id();


      $query = "SELECT $fname.*," . $fname . "_bodies.body FROM $fname, " . $fname . "_bodies " .
         "WHERE $fname.id=" . $fname . "_bodies.id ORDER by $fname.id";

      $result_1 = mysql_query( $query ) or die(mysql_error());


      echo "\nStart $fname\n\n";
      while( $row = mysql_fetch_array( $result_1 ) )
      {
         $old_parent = $row["parent"];
         $Text = $row["body"];
         $Text = eregi_replace("^<html>","",$Text);
         $Text = eregi_replace("</html>$","",$Text);
         $Subject = $row["subject"];
         $old_ID = $row["id"];
         $uid = $row["pid"];
         $Time = $row["datestamp"];

         echo $old_ID;

         if( $old_parent > 0 )
         {
            $result = mysql_query("SELECT ID as parent,PosIndex,Depth,Thread_ID FROM Posts " .
                                     "WHERE old_ID=$old_parent AND Forum_ID=$new_forum_ID")
               or die(mysql_error());

            if( mysql_num_rows($result) != 1 )
               die( "Unknown parent post" );

            extract(mysql_fetch_array($result));

            $result = mysql_query("SELECT MAX(AnswerNr) AS answer_nr " .
                                  "FROM Posts WHERE Parent_ID=$parent")
               or die(mysql_error());

            extract(mysql_fetch_array($result));

            if( !($answer_nr > 0) ) $answer_nr=0;
         }
         else
         {
            // New thread
            $answer_nr = 0;
            $PosIndex = '';
            $Depth = 0;
            $Thread_ID = -1;
            $parent = 0;
         }

         $PosIndex .= $order_str[$answer_nr/64] . $order_str[$answer_nr%64];
         $Depth++;
         $Text = trim($Text);
         $Subject = trim($Subject);

         $query = "INSERT INTO Posts SET " .
            "Forum_ID=$new_forum_ID, " .
            "Thread_ID=$Thread_ID, " .
            "Time=\"$Time\", " .
            "Lastchanged=\"$Time\", " .
            "Subject=\"" . mysql_real_escape_string($Subject) . "\", " .
            "Text=\"" . mysql_real_escape_string($Text) . "\", " .
            "User_ID=$uid, " .
            "Parent_ID=$parent, " .
            "AnswerNr=" . ($answer_nr+1) . ", " .
            "Depth=$Depth, " .
            "Approved=\"" . $row['approved'] . "\", " .
            "crc32=" . crc32($Text) . ", " .
            "PosIndex=\"$PosIndex\", " .
            "old_ID=$old_ID";

         mysql_query( $query ) or die(mysql_error());

         if( mysql_affected_rows() != 1)
            die( "mysql_insert_post" );

         echo "->" . mysql_insert_id() . "\n";

         if( !($parent > 0) )
         {
            $Thread_ID = mysql_insert_id();
            mysql_query("UPDATE Posts SET Thread_ID=ID WHERE ID=$Thread_ID")
                or die(mysql_error());

            if( mysql_affected_rows() != 1)
               die("mysql_update_post");
         }

      }

      recalculate_postsinforum($new_forum_ID);

      echo "\n\nFinished $fname\n";
   }

   echo "\n\n\nDone!!\n";
}
?>