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


   $encoding_used= get_request_arg( 'charset', LANG_DEF_CHARSET); //iso-8859-1 UTF-8

   if( isset($_REQUEST['rowhdr']) )
      $rowhdr= $_REQUEST['rowhdr'];
   else
      $rowhdr= 20;

   if( isset($_REQUEST['colwrap']) )
      $colwrap= $_REQUEST['colwrap'];
   else
      $colwrap= 'cut';

   if( isset($_REQUEST['colsize']) )
      $colsize= $_REQUEST['colsize'];
   else
      $colsize= 40;

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
      $$arg= trim(get_request_arg($arg));
   }


   start_html( 'data_report', 0, 
      "  table.tbl { border:0; background: #c0c0c0; }\n" .
      "  tr.row1 { background: #ffffff; }\n" .
      "  tr.row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   echo " <SCRIPT language=\"JavaScript\" type=\"text/javascript\"><!-- \n";
   echo "   function row_click(row,rcl) {
     row.className=((row.className=='hil')?rcl:'hil');
   }\n";
//     row.bgColor=((row.bgColor.toLowerCase()==hcol)?rcol:hcol);
   echo "\n//-->\n</SCRIPT>\n";


   $dform = new Form('dform', 'data_report.php#result', FORM_POST, true );

   foreach( $arg_array as $arg => $word)
   {
      $dform->add_row( array( 'DESCRIPTION', $word,
                                 'TEXTAREA', $arg, 80, 2, $$arg ) );
   }

   $dform->add_row( array(
      'HIDDEN', 'charset', $encoding_used,
      'CELL', 9, 'align="center"',
      'OWNHTML', '<INPUT type="submit" name="apply" accesskey="x" value="Apply [&x]">',
      'TEXT', '&nbsp;&nbsp;col size:&nbsp;',
      'TEXTINPUT', 'colsize', 3 , 3, $colsize,
      'RADIOBUTTONS', 'colwrap', array('cut'=>'cut','wrap'=>'wrap',''=>'none',), $colwrap,
      ) );

   $dform->echo_string(1);
   echo "<p>&nbsp;</p>\n";


   while( $apply && $select )
   {
      $apply=0;
      $query= '';
      $uri= 'http://' . $HOSTNAME . @$_SERVER['PHP_SELF'] . '?';
      foreach( $arg_array as $arg => $word )
      {
         if( $$arg )
         {
            $query.= $word . ' ' . $$arg . ' ';
            $uri.= $arg . '=' . urlencode($$arg) . URI_AMP;
         }
      }
      $uri.= 'apply=1#result';

      echo 'Query&gt; ' . anchor( $uri, textarea_safe($query).';') . "&nbsp;<br>\n";


      echo "<A name=\"result\"></A><br>\n";
      if( ($n=echo_query( $query, $rowhdr, $colsize, $colwrap)) < 0 ) break;

      $s= "SELECT '$n' as 'Rows'"
         . ",NOW() as 'Mysql time'"
         . ",FROM_UNIXTIME($NOW) as 'Server time'"
         . ",'" . date('Y-m-d H:i:s', $NOW) . "' as 'Local time'"         
         . ",'" . gmdate('Y-m-d H:i:s', $NOW) . "' as 'GMT time'"         
         //. ",'".mysql_info()."' as 'Infos'"
         ;
      if( echo_query( $s, 0, 0, 0) < 0 ) break;

      if( echo_query( 'EXPLAIN '.$query, 0, 0, 0) < 0 ) break;

      echo 'Query&gt; ' . anchor( $uri, textarea_safe($query).';') . "&nbsp;<br>\n";
   }

   end_html();
}

function echo_query( $query, $rowhdr=20, $colsize=40, $colwrap='cut' )
{
   //kill sensible fields from a query like "SELECT Password as pwd FROM Players"
   $query= preg_replace( "%(Password|Sessioncode|Email)%is", "***", $query);

   $result = mysql_query( $query );

   $mysqlerror = @mysql_error();
   if( $mysqlerror )
   {
      echo "<p>Error: ".textarea_safe($mysqlerror)."</p>";
      return -1;
   }

   if( !$result )
      return 0;

   $numrows = @mysql_num_rows($result);
   if( $numrows<=0 )
   {
      @mysql_free_result( $result);
      return 0;
   }

   $c=2;
   $i=0;
   echo "\n<table title='$numrows rows' class=tbl cellpadding=4 cellspacing=1>\n";
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $c=3-$c;
      $i++;
      if( $i==1 or ($rowhdr>1 && ($i%$rowhdr)==1) )
      {
         echo "<tr>\n";
         foreach( $row as $key => $val )
         {
            echo "<th>$key</th>";
         }
         echo "\n</tr>";
      }
      //onClick onmousedown ondblclick
      echo "<tr class=row$c ondblclick=\"row_click(this,'row$c')\">\n";
      foreach( $row as $key => $val )
      {
         //remove sensible fields from a query like "SELECT * FROM Players"
         switch( $key )
         {
            case 'Password':
            case 'Sessioncode':                  
            case 'Email':
               if ($val) $val= '***';
               break;
            case 'Debug':
               if ($val)
                  $val= preg_replace( "%(passwd=)[^&]*%is", "\\1***", $val);
               break;
         }
         $val= textarea_safe($val);
         if( $colsize>0 )
         {
            if( $colwrap==='wrap' )
               $val= wordwrap( $val, $colsize, '<br>', 1);
            else if( $colwrap==='cut' )
               $val= substr( $val, 0, $colsize);
         }
         echo "<td title='$key#$i' nowrap>$val</td>";
      }
      echo "\n</tr>";
   }
   mysql_free_result( $result);
   echo "\n</table><br>\n";

   return $numrows;
}

?>