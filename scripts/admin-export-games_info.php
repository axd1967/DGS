<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

function error( $err, $debugmsg=NULL )
{
   $title = str_replace('_',' ',$err);
   list( $xerr, $uri ) = err_log( $uhandle, $err, $debugmsg );
   exit;
}

require_once 'include/std_functions.php';
require_once 'include/rating.php';


{
   // USAGE: Adjust query below to fit your needs!!

   // This script should not run via webserver, but from command-line
   if( isset($_SERVER["SERVER_NAME"]) )
      exit;

   // check args
   $errors = array();
   if( $argc == 2 )
   {
      $cmd = $argv[1];
      if( $cmd != 'count' && $cmd != 'exec' )
         $errors[] = "Bad command '$cmd', allowed are [count|exec]";
   }
   else
      $errors[] = 'Bad arguments';
   if( count($errors) )
   {
      $err_text = implode('], [', $errors);
      echo "ERROR: [$err_text]\n";
      echo "Usage: $argv[0] <command>\n";
      echo "Options:\n";
      echo "   <command> = count (=count games to load), exec (=export games-info)\n";
      echo "\n";
      exit;
   }


   connect2mysql();

   $out_dir = "GAMESINFO"; // output-directory
   if( !file_exists($out_dir) )
      create_dir( $out_dir );

   $qsql = new QuerySQL(
      SQLP_FIELDS,
         'G.ID AS gid',
         'YEAR(G.Lastchanged) AS Year',
         'G.*',
         'UNIX_TIMESTAMP(G.Starttime) AS X_Starttime',
         'UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged',
      SQLP_FROM,
         'Games AS G',
      SQLP_WHERE,
         "G.Status='FINISHED'",
      SQLP_ORDER,
         'G.Lastchanged ASC',
         'G.ID ASC'
   );
   $result = db_query( 'export_gamesinfo.find_games', $qsql->get_select() );

   $rows = @mysql_num_rows($result);
   echo "There are $rows games to export ...\n";
   if( $cmd != 'exec' )
      exit;

   $cnt = 0;
   $begin_secs = time();
   $filehandle = null;
   $curr_year = -1;
   while( $row = mysql_fetch_array( $result ) )
   {
      $cnt++;
      $timediff = time() - $begin_secs;
      if( !($cnt % 10000) )
         echo "Retrieving game $gid ($cnt / $rows) ... needed $timediff secs so far ...\n";

      write_game_info( $curr_year, $row );
   }
   mysql_free_result($result);

   close_file();

   echo "Export finished.\n";
}


function write_game_info( &$curr_year, $row )
{
   global $filehandle, $out_dir;

   $year = $row['Year'];
   if( $year != $curr_year )
   {
      close_file();
      $filename = sprintf( "%s/DGS-games_info-%04d.csv", $out_dir, $year );
      $filehandle = @fopen( $filename, 'wb' );
      @fwrite( $filehandle, build_game_info() );
   }
   $curr_year = $year;

   @fwrite( $filehandle, build_game_info($row) );
}

function close_file()
{
   global $filehandle;
   if( !is_null($filehandle) )
      fclose($filehandle);
   $filehandle = null;
}

function build_game_info( $row=null )
{
   if( is_null($row) ) // headers
   {
      return "GameID;StartTime;EndTime;Size;Moves;Rated;Ruleset;Handicap;Komi;Score;BlackID;WhiteID;BlackStartRating;WhiteStartRating;BlackEndRating;WhiteEndRating;TimeLimit\r\n";
   }
   else
   {
      return implode(';', array(
         $row['ID'],
         date('Y-m-d H:i:s', $row['X_Starttime'] ),
         date('Y-m-d H:i:s', $row['X_Lastchanged'] ),
         $row['Size'],
         $row['Moves'],
         ($row['Rated'] == 'N') ? 0 : 1,
         ($row['Ruleset'] == 'CHINESE') ? 'CH' : 'JP',
         $row['Handicap'],
         $row['Komi'],
         $row['Score'],
         $row['Black_ID'],
         $row['White_ID'],
         $row['Black_Start_Rating'],
         $row['White_Start_Rating'],
         $row['Black_End_Rating'],
         $row['White_End_Rating'],
         TimeFormat::echo_time_limit( $row['Maintime'], $row['Byotype'], $row['Byotime'], $row['Byoperiods'],
               TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE),
      )) . "\r\n";
   }
}

function create_dir( $path )
{
   // NOTE: PHP4 don't have recursive-parameter yet
   if( !@mkdir($path) )
      error('assert', "export_gamesinfo.create_dir($path)");
}
?>
