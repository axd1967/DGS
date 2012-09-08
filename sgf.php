<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


require_once 'include/sgf_builder.php';


/* Actual REQUEST calls used:
      quick_mode=0|1       : 1 = ignore errors
      gid=1234|game1234    : game-id
      no_cache=0|1         : 1 = disable cache (expire-header)
      owned_comments=0|1|N : 1 = if set try to return private comments (for game-players only) including private notes on game,
                             0 = return only public comments (default)
                             N = return NO comments or notes
      mpg=<INT>            : flags for multi-player-game: 0 = default, 1 (bit #0) = no-user-per-node
      inline=0|1           : 1 = use Content-Disposition type of "inline" to directly start assigned application
      bulk=0|1             : 1 = use special filename-pattern (omit handicap if =0 and result if unfinished game):
                             DGS-<gid>_YYYY-MM-DD_<rated=R|F><size>(H<handi>)K<komi>(=<result>)_<white>-<black>.sgf
 */

$quick_mode = (boolean)@$_REQUEST['quick_mode'];
if( $quick_mode )
   $TheErrors->set_mode(ERROR_MODE_PRINT);


if( $is_down )
{
   error('server_down');
}
else
{
   // see the Expires header below and 'no_cache'-URL-arg
   //disable_cache( $NOW, $NOW+5*60);

   connect2mysql();

   // parse args
   $gid = (int)@$_REQUEST['gid'];
   if( $gid <= 0 )
   {
      if( eregi("game([0-9]+)", @$_SERVER['REQUEST_URI'], $tmp) )
         $gid = $tmp[1];
   }
   $gid = (int)$gid;
   if( $gid <= 0 )
      error('unknown_game', "sgf.check.game($gid)");

   $use_cache = !((bool)@$_REQUEST['no_cache']);
   #$use_cache = false;

   $owned_comments = @$_REQUEST['owned_comments'];
   $no_comments = false;
   if( strtolower($owned_comments) == 'n' )
   {
      $owned_comments = 0;
      $no_comments = true;
   }

   $opt_mpg = (int)@$_REQUEST['mpg'];

   $sgf = new SgfBuilder( $gid, /*use_buf*/false );
   if( $opt_mpg & 1 )
      $sgf->mpg_node_add_user = false;

   $row = $sgf->load_game_info();
   extract($row);

   $filename = $sgf->build_filename_sgf( @$_REQUEST['bulk'] );

   // owned_comments: BLACK|WHITE=viewed by B/W-game-player (also for MP-game), DAME=viewed by other user
   $owned_uid = 0;
   if( $owned_comments )
   {
      $owned_comments = DAME;
      $cookie_handle = safe_getcookie('handle');
      if( $sgf->is_mpgame )
      {
         $sgf->find_mpg_user( $cookie_handle );
         if( is_array($sgf->mpg_active_user) )
         {
            if( $sgf->mpg_active_user['Sessioncode'] == safe_getcookie('sessioncode')
                  && $sgf->mpg_active_user['X_Sessionexpire'] >= $NOW )
            {
               $owned_uid = $sgf->mpg_active_user[0];
               $owned_comments = BLACK; // <>DAME
            }
         }
      }
      elseif( strcasecmp($Blackhandle, $cookie_handle) == 0 )
      {
         if( $Blackscode == safe_getcookie('sessioncode') && $Blackexpire >= $NOW )
         {
            $owned_comments = BLACK ;
            $owned_uid = $Black_uid;
         }
      }
      elseif( strcasecmp($Whitehandle, $cookie_handle) == 0 )
      {
         if( $Whitescode == safe_getcookie('sessioncode') && $Whiteexpire >= $NOW )
         {
            $owned_comments = WHITE ;
            $owned_uid = $White_uid;
         }
      }
   }
   else
      $owned_comments = DAME;

   // load GamesNotes for player
   if( $sgf->include_games_notes && ($owned_comments != DAME) && ($owned_uid > 0) )
      $sgf->load_player_game_notes( $owned_uid );

   $sgf->load_trimmed_moves( !$no_comments );


   // output HTTP-header
   header( 'Content-Type: application/x-go-sgf' );
   // default for content-disposition is "attachment" because of "inline"-problems for some mobile-devices
   $disposition_type = ( @$_REQUEST['inline'] ) ? 'inline' : 'attachment';
   header( "Content-Disposition: $disposition_type; filename=\"$filename.sgf\"" );
   header( "Content-Description: PHP Generated Data" );

   //to allow some mime applications to find it in the cache
   if( $use_cache )
   {
      header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*60));
      header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));
   }

   // output SGF
   $sgf->build_sgf( $filename, $owned_comments );
}//main

?>
