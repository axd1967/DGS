<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*
 * Script to export single game as SQL, ready to be inserted in other database.
 * Used to be able to export games to import to test database.
 *
 * NOTE: most stuff copied from 'scripts/data_export.php'
 */


chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

define('QUOTE', '`'); //backquote
define('SPACE', '&nbsp;&nbsp;&nbsp;');

{
   connect2mysql();

   $gid = get_request_arg('gid');

   if( @$_REQUEST['export'] && is_numeric($gid) && $gid > 0 )
   {
      export_game($gid);
   }
   else
   {
      start_html( 'sql_game_export', true );
      echo "<center>", "<h3 class=\"Header\">Export Game as SQL</h3>\n";

      $page = 'sql_game_export.php';
      $dform = new Form('dform', 'sql_game_export.php', FORM_GET, true);
      $dform->add_row( array(
         'DESCRIPTION', 'Game ID',
         'TEXTINPUT',   'gid', 10, 10, $gid,
         'SUBMITBUTTON', 'export', 'Export Game Data',
         ));
      $dform->echo_string(1);

      echo "<br><br>",
         anchor( $base_path.'scripts/'.$page, 'Export new game' ), SPACE,
         anchor( $base_path.'status.php', 'Return to DGS Status' ), SPACE,
         "\n";

      echo "</center>\n";
      end_html();
   }
}

// return array( success=true|false, resulttext|errtext )
function export_game( $gid )
{
   global $NOW;

   $sql_games = insert_set( 'Games', "SELECT * from Games WHERE ID='$gid' LIMIT 1", true );
   if( $sql_games[0] <= 0 )
      return array( false, $sql_games[1] );

   // skip ID
   $sql_moves = insert_set( 'Moves', "SELECT * from Moves WHERE gid='$gid' ORDER BY ID", false, array( 'ID' ) );
   if( $sql_moves[0] <= 0 )
      return array( false, $sql_moves[1] );

   $sql_ratinglog = insert_set( 'Ratinglog', "SELECT * from Ratinglog WHERE gid='$gid' LIMIT 2", false, array( 'ID' ) );
   if( $sql_ratinglog[0] < 0 ) // optional
      return array( false, $sql_ratinglog[1] );

   // direct output
   $date = date('Y-M-d_His', $NOW);
   $tmp_file = "export_game_dgs-$date.sql";
   $expire = gmdate('D, d M Y H:i:s',$NOW - 1000) . ' GMT'; // expire immediately
   header( 'Content-type: text/plain' ); // ISO-8859-1
   header( "Content-Disposition: inline; filename=\"$tmp_file\"" );
   header( "Content-Description: PHP Generated Data" );
   header('Expires: ' . $expire );
   header('Last-Modified: ' . $expire );

   echo
      "-- Export of Game #$gid\n\n",
      "-- Table Games\n",
         $sql_games[2], "\n",
      "-- Table Moves\n",
         sprintf( "-- DELETE FROM %s WHERE gid='$gid'", quoteit('Moves') ), "\n",
         $sql_moves[2], "\n",
      "-- Table Ratinglog\n",
         sprintf( "-- DELETE FROM %s WHERE gid='$gid'", quoteit('Ratinglog') ), "\n",
         $sql_ratinglog[2], "\n",
      "\n";

   exit;
}

// return array( -1=error|0=no-rows|rowcount, errtext|'', resulttext|'' )
function insert_set( $table, $query, $replace=true, $skip_fields=null )
{
   $result = mysql_query($query);
   if( !$result )
      return array( -1, "MySQL-error: " . mysql_error(), '' );

   $mysqlerror = @mysql_error();
   if( $mysqlerror )
      return array( -1, "Error: ".textarea_safe($mysqlerror), '' );
   if( !$result )
      return array( 0, "No rows found for table [$table] for query [$query]", '' );

   if( @mysql_num_rows($result) <= 0 )
   {
      $output = array( 0, "No rows found for table [$table] for query [$query]", '' );
   }
   else
   {
      if( is_null($skip_fields) )
         $skip_fields = array();
      $skip_norm = array(); // normalized
      foreach( $skip_fields as $field )
         $skip_norm[strtolower($field)] = 1;

      $text = '';
      $rowcnt = 0;
      while( $row = mysql_fetch_assoc( $result ) )
      {
         $rowcnt++;
         $str = '';
         foreach( $row as $key => $val )
         {
            if( !isset($skip_norm[strtolower($key)]) )
               $str .= ',' . $key.'='.safe_value($val);
         }
         if( $str )
            $text .= ($replace ? 'REPLACE' : 'INSERT' )
               . ' INTO ' . quoteit($table,QUOTE) . ' SET ' . substr($str,1) . ";\n";
      }
      $output = array( $rowcnt, '', $text );
   }
   @mysql_free_result($result);

   return $output;
}

function safe_value( $val=NULL )
{
   if( is_null($val) )
      return 'NULL';
   elseif( !is_numeric($val) )
      return "'".mysql_addslashes($val)."'";
   else
      return $val;
} //safe_value

function quoteit( $mixed, $quote='`' )
{
   if( is_array( $mixed ) )
   {
      $result = array();
      foreach( $mixed AS $key => $val)
         $result[$key] = quoteit($val);
      return $result;
   }
   if( !empty($mixed) || is_numeric($mixed) )
      return $quote . trim($mixed, " '`$quote") . $quote;
   return $mixed;
}

?>
