<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

chdir( '../' );
require_once( "include/std_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low');


   start_html( 'translation_consistency', 0);

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
echo ">>>> Most of them needs manual fixes.";
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor($_SERVER['PHP_SELF']           , 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p>(just show needed queries)"
         ."<br>".anchor($_SERVER['PHP_SELF']           , 'Show it again')
         ."<br>".anchor($_SERVER['PHP_SELF'].'?do_it=1', '[Validate it]')
         ."</p>";
   }



   $row = mysql_single_fetch( 'FAQtransl_consistency.get_faq_group_id',
            "SELECT ID FROM TranslationGroups WHERE Groupname='FAQ'" )
      or error('internal_error', 'FAQtransl_consistency.get_faq_group_id');

   $FAQ_group = $row['ID'];



   echo "<hr>FAQ orphean texts (TranslationTexts=>FAQ but FAQ!=>TranslationTexts):";

   $query = "SELECT TranslationTexts.ID as TID,TranslationTexts.*,FAQ.*"
     ." FROM TranslationTexts"
     ." LEFT JOIN FAQ ON TranslationTexts.Ref_ID=FAQ.ID"
     ." WHERE TranslationTexts.Ref_ID>0"
     ." ORDER BY TID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $TID = (int)@$row['TID'];
      $QID = (int)@$row['Question'];
      $AID = (int)@$row['Answer'];
      if( $QID!=$TID && $AID!=$TID )
      {
         echo "<br>Text.$TID =&gt; FAQ." . $row['Ref_ID']
            . " =&gt; Text.$QID + Text.$AID" ;
         /*
         dbg_query("UPDATE TranslationTexts SET Ref_ID=0 " .
                      "WHERE ID=;;;; LIMIT 1" );
         */
      }
   }

   echo "<br>FAQ orphean texts done.\n";


   echo "<hr>FAQ entries without translations:";

   $query = "SELECT FAQ.ID as FID,TranslationTexts.*,FAQ.*"
     ." FROM FAQ"
     ." LEFT JOIN TranslationTexts ON TranslationTexts.ID=FAQ.Question"
     ." WHERE FAQ.ID>0 AND FAQ.Question>0"
     ." ORDER BY FID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $FID = (int)@$row['FID'];
      $QID = (int)@$row['Question'];
      $RID = (int)@$row['Ref_ID'];
      if( $FID!=$RID )
      {
         echo "<br>FAQ.$FID =&gt; QText.$QID =&gt; $RID";
         /*
         dbg_query("UPDATE TranslationTexts SET Ref_ID=0 " .
                      "WHERE ID=;;;; LIMIT 1" );
         */
      }
   }

   $query = "SELECT FAQ.ID as FID,TranslationTexts.*,FAQ.*"
     ." FROM FAQ"
     ." LEFT JOIN TranslationTexts ON TranslationTexts.ID=FAQ.Answer"
     ." WHERE FAQ.ID>0 AND FAQ.Answer>0"
     ." ORDER BY FID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $FID = (int)@$row['FID'];
      $AID = (int)@$row['Answer'];
      $RID = (int)@$row['Ref_ID'];
      if( $FID!=$RID )
      {
         echo "<br>FAQ.$FID =&gt; AText.$AID =&gt; $RID";
         /*
         dbg_query("UPDATE TranslationTexts SET Ref_ID=0 " .
                      "WHERE ID=;;;; LIMIT 1" );
         */
      }
   }

   echo "<br>FAQ entries without translations done.\n";


   echo "<hr>Translation texts without foundingroup:";

   $query = "SELECT T.ID as TID, T.*, I.*"
     ." FROM TranslationTexts AS T"
     ." LEFT JOIN TranslationFoundInGroup AS I ON I.Text_ID=T.ID"
     ." WHERE T.ID>0 AND T.Text>''"
     ." ORDER BY TID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $TID = (int)@$row['TID'];
      $GID = (int)@$row['Group_ID'];
      if( $GID<=0 )
      {
         echo "<br>Text.$TID =&gt; InGroup.$GID";
         /*
         dbg_query("UPDATE TranslationTexts SET Ref_ID=0 " .
                      "WHERE ID=;;;; LIMIT 1" );
         */
      }
   }

   echo "<br>Translation texts without foundingroup done.\n";

//Multiple groups translation texts
//SELECT COUNT(*) as cnt,Text_ID FROM TranslationFoundInGroup GROUP BY Text_ID ORDER BY cnt desc LIMIT 100;


   echo "<hr>Translation texts without group:";

   $query = "SELECT T.ID as TID, T.*, I.*, G.*"
     ." FROM (TranslationTexts AS T, TranslationFoundInGroup AS I)"
     ." LEFT JOIN TranslationGroups AS G ON I.Group_ID=G.ID"
     ." WHERE T.ID>0 AND T.Text>'' AND I.Text_ID=T.ID"
     ." ORDER BY TID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $TID = (int)@$row['TID'];
      $GID = (int)@$row['Group_ID'];
      $nam = (string)@$row['Groupname'];
      if( $nam == '' )
      {
         echo "<br>Text.$TID =&gt; InGroup.$GID =&gt; Group.$nam";
         /*
         dbg_query("UPDATE TranslationTexts SET Ref_ID=0 " .
                      "WHERE ID=;;;; LIMIT 1" );
         */
      }
   }

   echo "<br>Translation texts without group done.\n";


   echo "<hr>FAQ entries without 'FAQ' group:";

   $query = "SELECT F.ID as FID,F.*,I.*,G.*"
     ." FROM (FAQ as F,TranslationTexts AS T)"
     ." LEFT JOIN TranslationFoundInGroup AS I ON I.Text_ID=F.Question"
     ." LEFT JOIN TranslationGroups AS G ON I.Group_ID=G.ID"
     ." WHERE F.ID>0 AND F.Question>0 AND T.ID=F.Question AND T.Text>''"
     ." ORDER BY FID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $FID = (int)@$row['FID'];
      $QID = (int)@$row['Question'];
      $GID = (int)@$row['Group_ID'];
      $nam = (string)@$row['Groupname'];
      if( $GID!=$FAQ_group or $nam!='FAQ' )
      {
         echo "<br>FAQ.$FID =&gt; QText.$QID =&gt; InGroup.$GID =&gt; Group.$nam";
         dbg_query("REPLACE TranslationFoundInGroup SET Text_ID=$QID,Group_ID=$FAQ_group");
      }
   }

   $query = "SELECT F.ID as FID,F.*,I.*,G.*"
     ." FROM (FAQ as F,TranslationTexts AS T)"
     ." LEFT JOIN TranslationFoundInGroup AS I ON I.Text_ID=F.Answer"
     ." LEFT JOIN TranslationGroups AS G ON I.Group_ID=G.ID"
     ." WHERE F.ID>0 AND F.Answer>0 AND T.ID=F.Answer AND T.Text>''"
     ." ORDER BY FID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $FID = (int)@$row['FID'];
      $AID = (int)@$row['Answer'];
      $GID = (int)@$row['Group_ID'];
      $nam = (string)@$row['Groupname'];
      if( $GID!=$FAQ_group or $nam!='FAQ' )
      {
         echo "<br>FAQ.$FID =&gt; AText.$AID =&gt; InGroup.$GID =&gt; Group.$nam";
         dbg_query("REPLACE TranslationFoundInGroup SET Text_ID=$AID,Group_ID=$FAQ_group");
      }
   }

   echo "<br>FAQ entries without 'FAQ' group done.\n";


   echo "<hr>Done!!!\n";
   end_html();
}
?>
