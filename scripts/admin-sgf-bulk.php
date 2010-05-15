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

require_once 'include/sgf_builder.php';


{
   // This script should not run via webserver, but from command-line
   if( isset($_SERVER["SERVER_NAME"]) )
      exit;

   // check args
   $errors = array();
   if( $argc == 4 )
   {
      $cmd = $argv[1];
      if( $cmd != 'count' && $cmd != 'exec' )
         $errors[] = "Bad command '$cmd', allowed are [count|exec]";
      $sleep_games = (int)abs($argv[2]);
      $sleep_secs  = (int)abs($argv[3]);
   }
   else
      $errors[] = 'Bad arguments';
   if( count($errors) )
   {
      $err_text = implode('], [', $errors);
      echo "ERROR: [$err_text]\n";
      echo "Usage: $argv[0] <command> <sleep-games> <sleep-secs>\n";
      echo "Options:\n";
      echo "   <command> = count (=count games to download), exec (=download SGFs)\n";
      echo "   <sleep-games> = how many games to download for next sleep of <sleep-secs> seconds\n";
      echo "\n";
      exit;
   }


   connect2mysql();

   $out_dir = "SGF"; // output-directory
   if( !file_exists($out_dir) )
      create_dir( $out_dir );

   $qsql = new QuerySQL(
      SQLP_FIELDS,
         'G.ID AS gid',
         'YEAR(G.Lastchanged) AS Year',
         'MONTH(G.Lastchanged) AS Month',
         'DAY(G.Lastchanged) AS Day',
      SQLP_FROM,
         'Games AS G',
      SQLP_WHERE,
         'G.Moves > 10',
         'G.Size = 9',
         'G.Score BETWEEN -1000 AND 1000', // no Timeouts
         "G.Status='FINISHED'",
      SQLP_ORDER,
         'G.ID ASC'
   );
   $result = db_query( 'sgf_bulk.find_games', $qsql->get_select() );

   $rows = @mysql_num_rows($result);
   echo "There are $rows games to download ...\n";
   if( $cmd != 'exec' )
      exit;

   $cnt = 0;
   $cnt_games = $sleep_games;
   $begin_secs = time();
   while( $row = mysql_fetch_array( $result ) )
   {
      $cnt++;
      $timediff = time() - $begin_secs;
      echo "Retrieving game $gid ($cnt / $rows) ... needed $timediff secs so far ...\n";

      extract($row);
      $year  = sprintf('%04d', $Year);
      $month = sprintf('%02d', $Month);
      $day   = sprintf('%02d', $Day);

      // build SGF in buffer
      $sgf = new SgfBuilder( $gid, /*use_buf*/true );
      $sgf->load_game_info();
      $filename = $sgf->build_filename_sgf( /*bulk*/true );
      $sgf->load_trimmed_moves( /*comments*/false );
      $sgf->build_sgf( $filename, $owned_comments );

      // NOTE: adjust dir-creation to new path-pattern
      $path = "$out_dir/$year/$month";
      if( !file_exists($path) )
      {
         if( !file_exists("$out_dir/$year") )
            create_dir( "$out_dir/$year" );
         create_dir( $path );
      }

      // write SGF to file
      write_to_file( "$path/{$filename}.sgf", $sgf->SGF, true );

      if( $cnt_games-- < 0 )
      {
         $cnt_games = $sleep_games;
         echo "... sleeping for $sleep_secs secs\n";
         sleep( $sleep_secs );
      }
   }
   mysql_free_result($result);
}

function create_dir( $path )
{
   // NOTE: PHP4 don't have recursive-parameter yet
   if( !@mkdir($path) )
      error('assert', "sgf_bulk.create_dir($path)");
}
?>
