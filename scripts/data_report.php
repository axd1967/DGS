<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );

{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");


   $encoding_used= get_request_arg( 'charset', 'iso-8859-1'); //iso-8859-1 utf-8

   $apply= @$_REQUEST['apply'];

   $arg_array = array(
      'select' => 'SELECT',
      'from' => 'FROM',
      'join' => 'LEFT JOIN',
      'where' => 'WHERE',
      'group' => 'GROUP BY',
      'having' => 'HAVING',
      'order' => 'ORDER BY',
      'limit' => 'LIMIT',
      );
   foreach( $arg_array as $arg => $word)
   {
      $$arg= get_request_arg($arg);
   }


   header ('Content-Type: text/html; charset='.$encoding_used); // Character-encoding

   echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n";
   echo "<HTML><HEAD>\n";
   echo " <META http-equiv=\"Content-Type\" content=\"text/html; charset=$encoding_used\">\n";
   echo " <TITLE>data_report</TITLE>\n";

   echo " <STYLE TYPE=\"text/css\">
  table.tbl {
   border:0;
   background: #c0c0c0;
  }
  tr.row1 {
   background: #ffffff;
  }
  tr.row2 {
   background: #dddddd;
  }
  tr.hil {
   background: #ffb010;
  }
 </STYLE>\n";

   echo " <SCRIPT language=\"JavaScript\"><!-- \n";
   echo "   function row_click(row,rcl) {
     row.className=((row.className=='hil')?rcl:'hil');
   }\n";
//     row.bgColor=((row.bgColor.toLowerCase()==hcol)?rcol:hcol);
   echo " --></SCRIPT>\n";

   echo "</HEAD>\n";
   echo "<BODY>\n";



   $dform = new Form('dform', 'data_report.php', FORM_POST, true );

   foreach( $arg_array as $arg => $word)
   {
      $dform->add_row( array( 'DESCRIPTION', $word,
                                 'TEXTAREA', $arg, 80, 2, $$arg ) );
   }
   $dform->add_row( array( 'CELL', 9, 'align="center"',
      'HIDDEN', 'charset', $encoding_used,
      'OWNHTML', '<INPUT type="submit" name="apply" accesskey="a" value="A-pply">',
      ) );

   $dform->echo_string();


   if( $apply && $select )
   {
      $query= '';
      foreach( $arg_array as $arg => $word )
      {
         if( $$arg )
            $query.= $word . ' ' . $$arg . ' ';
      }

      echo 'Query&gt; ' . $query . ';<p>';
      $result = mysql_query( $query );
      $mysqlerror = @mysql_error();

      if( $mysqlerror )
      {
         echo "<p>Erreur: $mysqlerror<p>";
      }
      else if( $result && @mysql_num_rows($result)>0 )
      {
         $c=2;
         $i=0;
         echo "\n<table class=tbl cellpadding=4 cellspacing=1>\n";
         while( $row = mysql_fetch_assoc( $result ) )
         {
            $c=3-$c;
            if( ($i=($i%20)+1) == 1 )
            {
               echo "<tr>\n";
               foreach( $row as $key => $val )
               {
                  echo "<th>$key</th>";
               }
               echo "\n</tr>";
            }
            echo "<tr class=row$c onmousedown=\"row_click(this,'row$c')\">\n";
            foreach( $row as $key => $val )
            {
               echo "<td nowrap>" . textarea_safe($val) . "</td>";
            }
            echo "\n</tr>";
         }
         echo "\n</table>\n";
      }
   }

   echo "</BODY>\n";
   echo "</HTML>\n";
}
?>