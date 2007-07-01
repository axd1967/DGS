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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/rating.php" );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $observe = isset($_GET['observe']);
   if( $observe )
   {
      $finished = false; //by definition
      $uid = 0; //from $player_row["ID"] list
      $all = false;
   }
   else
   {
      $finished = isset($_GET['finished']);
      $uid = @$_GET['uid'];
      $all = ($uid == 'all');
      if( !$all )
      {
         get_request_user( $uid, $uhandle, true);
         if( $uhandle )
            $where = "Handle='".mysql_addslashes($uhandle)."'";
         elseif( $uid > 0 )
            $where = "ID=$uid";
         else
            error("no_uid");

         $result = mysql_query( "SELECT ID, Name, Handle FROM Players WHERE $where" )
            or error('mysql_query_failed', 'show_games.find_player');

         if( @mysql_num_rows($result) != 1 )
            error("unknown_user");

         $user_row = mysql_fetch_assoc($result);
         $uid = $user_row['ID'];
      }
   }
   $running = !$observe && !$finished; // 'and' don't work here, why? :(

   if( $observe )
   {
      $tableid = 'observed';
      $page = 'show_games.php?observe=1'.URI_AMP;
      $column_set_name = "ObservedGamesColumns";
      $fprefix = 'o';
   }
   else if( $finished )
   {
      $tableid = 'finished';
      $page = "show_games.php?uid=$uid".URI_AMP."finished=1".URI_AMP;
      $column_set_name = "FinishedGamesColumns";
      $fprefix = 'f';
   }
   else
   {
      $tableid = 'running';
      $page = "show_games.php?uid=$uid".URI_AMP;
      $column_set_name = "RunningGamesColumns";
      $fprefix = 'r';
   }

   // table filters
   $gfilter = new SearchFilter( $fprefix );
   $gfilter->add_filter( 1, 'Numeric', 'Games.ID', true, array( FC_SIZE => 8 ) );
   $gfilter->add_filter( 6, 'Numeric', 'Games.Size', true);
   $gfilter->add_filter( 7, 'Numeric', 'Games.Handicap', true);
   $gfilter->add_filter( 8, 'Numeric', 'Games.Komi', true);
   $gfilter->add_filter( 9, 'Numeric', 'Games.Moves', true);
   $gfilter->add_filter(13, 'RelativeDate', 'Games.Lastchanged', true);
   $gfilter->add_filter(14, 'RatedSelect', 'Rated', true);
   if ( !$observe and !$all )
   {
      $gfilter->add_filter( 3, 'Text',   'Name',   true);
      $gfilter->add_filter( 4, 'Text',   'Handle', true);
      $gfilter->add_filter(16, 'Rating', 'Rating2', true);
   }
   if ( $running and !$all )
   {
      $gfilter->add_filter( 5, 'Selection',     # filter on my color (not on who-to-move)
            array( T_('All') => '',
                   'B' => new QuerySQL( SQLP_HAVING, 'iamBlack=1' ),
                   'W' => new QuerySQL( SQLP_HAVING, 'iamBlack=0' ) ),
            true);
      $gfilter->add_filter(12, 'BoolSelect', 'Weekendclock', true);
      $gfilter->add_filter(15, 'RelativeDate', 'Lastaccess', true);
      $gfilter->add_filter(23, 'Rating', 'startRating', true, array( FC_ADD_HAVING => 1 ) );
   }
   if ( $finished )
   {
      $gfilter->add_filter(10, 'Score', 'Score', true);
      $gfilter->add_filter(11, 'Selection',
            array( T_('All')  => '',
                   T_('Won')  => new QuerySQL( SQLP_HAVING, 'Win=1' ),
                   T_('Lost') => new QuerySQL( SQLP_HAVING, 'Win=-1' ),
                   T_('Jigo') => 'Score=0' ),
            true);
      $gfilter->add_filter(12, 'RelativeDate', 'Lastchanged', true);
      if ( $all )
      {
         $gfilter->add_filter(27, 'Rating', 'Black_End_Rating', true);
         $gfilter->add_filter(30, 'Rating', 'White_End_Rating', true);
         $gfilter->add_filter(28, 'RatingDiff', 'blog.RatingDiff', true);
         $gfilter->add_filter(31, 'RatingDiff', 'wlog.RatingDiff', true);
      }
      else // user
      {
         $gfilter->add_filter( 5, 'Selection',
               array( T_('All') => '', 'B' => "Black_ID=$uid", 'W' => "White_ID=$uid" ),
               true);
         $gfilter->add_filter(23, 'Rating', 'startRating', true, array( FC_ADD_HAVING => 1 ) );
         $gfilter->add_filter(24, 'Rating', 'endRating',   true, array( FC_ADD_HAVING => 1 ) );
         $gfilter->add_filter(25, 'RatingDiff', 'log.RatingDiff', true);
      }
   }
   if ( $observe or $all )
   {
      $gfilter->add_filter(17, 'Text',   'black.Name',   true);
      $gfilter->add_filter(18, 'Text',   'black.Handle', true);
      $gfilter->add_filter(19, 'Rating', 'black.Rating2', true);
      $gfilter->add_filter(26, 'Rating', 'Games.Black_Start_Rating', true);
      $gfilter->add_filter(20, 'Text',   'white.Name',   true);
      $gfilter->add_filter(21, 'Text',   'white.Handle', true);
      $gfilter->add_filter(22, 'Rating', 'white.Rating2', true);
      $gfilter->add_filter(29, 'Rating', 'Games.White_Start_Rating', true);
   }
   $gfilter->init(); // parse current value from _GET

   $gtable = new Table( $tableid, $page, $column_set_name );
   $gtable->register_filter( $gfilter );
   $gtable->set_default_sort( 'Lastchanged', 1, 'ID', 1);
   $gtable->add_or_del_column();

   // attach external URL-parameters to table
   $extparam = new RequestParameters();
   if ( $observe )
      $extparam->add_entry( 'observe', 1 );
   else
   {
      $extparam->add_entry( 'uid', $uid );
      if ( $finished )
         $extparam->add_entry( 'finished', 1 );
   }
   $gtable->add_external_parameters( $extparam, true ); // also for hiddens

   $order = $gtable->current_order_string();
   $limit = $gtable->current_limit_string();

   # table-column-ID usage
   #   views: OB=observe, FU=finished-user, FA=finished-all, RU=running-user, RA=running-all
   #   note: '> ' indicates a column not common to all views, usage given for specific views
   #
   # no: description of displayed info
   #  0: -
   #  1: ID
   #  2: sgf
   #  3: >  FU + RU (Opponent-Name)
   #  4: >  FU + RU (Opponent-Handle)
   #  5: >  FU (User-Color-Graphic), RU (2-Colors-Graphic)
   #  6: Size
   #  7: Handicap
   #  8: Komi
   #  9: Moves
   # 10: >  FU + FA (Score)
   # 11: >  FU (User-Score-graphic)
   # 12: >  FU + FA (Lastchanged as 'End date'), RU (Weekendclock)
   # 13: >  OB + RU + RA (Lastchanged as 'Last move')
   # 14: Rated
   # 15: >  RU (Opponents-LastAccess)
   # 16: >  FU + RU (User-Rating)
   # 17: >  OB + FA + RA (Black-Name)
   # 18: >  OB + FA + RA (Black-Handle)
   # 19: >  OB + FA + RA (Black-Rating)
   # 20: >  OB + FA + RA (White-Name)
   # 21: >  OB + FA + RA (White-Handle)
   # 22: >  OB + FA + RA (White-Rating)
   # 23: >  FU + RU (User-StartRating)
   # 24: >  FU (User-EndRating)
   # 25: >  FU (User-RatingDiff)
   # 26: >  OB + FA + RA (Black-StartRating)
   # 27: >  FA (Black-EndRating)
   # 28: >  FA (Black-EndRatingDiff)
   # 29: >  OB + FA + RA (White-StartRating)
   # 30: >  FA (White-EndRating)
   # 31: >  FA (White-EndRatingDiff)
   # 32: -

   // add_tablehead($nr, $descr, $sort=NULL, $desc_def=false, $undeletable=false, $attbs=NULL)
   $gtable->add_tablehead( 1, T_('ID'), 'ID', true, true, array( 'class' => 'Button') );
   $gtable->add_tablehead( 2, T_('sgf'));

   if( $observe )
   {
      $gtable->add_tablehead(17, T_('Black name'), 'blackName');
      $gtable->add_tablehead(18, T_('Black userid'), 'blackHandle');
      $gtable->add_tablehead(26, T_('Black start rating'), 'blackStartRating', true);
      $gtable->add_tablehead(19, T_('Black rating'), 'blackRating', true);
      $gtable->add_tablehead(20, T_('White name'), 'whiteName');
      $gtable->add_tablehead(21, T_('White userid'), 'whiteHandle');
      $gtable->add_tablehead(29, T_('White start rating'), 'whiteStartRating', true);
      $gtable->add_tablehead(22, T_('White rating'), 'whiteRating', true);
   }
   else if( $finished )
   {
      if( $all)
      {
         $gtable->add_tablehead(17, T_('Black name'), 'blackName');
         $gtable->add_tablehead(18, T_('Black userid'), 'blackHandle');
         $gtable->add_tablehead(26, T_('Black start rating'), 'blackStartRating', true);
         $gtable->add_tablehead(27, T_('Black end rating'), 'blackEndRating', true);
         $gtable->add_tablehead(19, T_('Black rating'), 'blackRating', true);
         $gtable->add_tablehead(28, T_('Black rating diff'), 'blackDiff', true);
         $gtable->add_tablehead(20, T_('White name'), 'whiteName');
         $gtable->add_tablehead(21, T_('White userid'), 'whiteHandle');
         $gtable->add_tablehead(29, T_('White start rating'), 'whiteStartRating', true);
         $gtable->add_tablehead(30, T_('White end rating'), 'whiteEndRating', true);
         $gtable->add_tablehead(22, T_('White rating'), 'whiteRating', true);
         $gtable->add_tablehead(31, T_('White rating diff'), 'whiteDiff', true);
      }
      else
      {
         $gtable->add_tablehead( 3, T_('Opponent'), 'Name');
         $gtable->add_tablehead( 4, T_('Userid'), 'Handle');
         $gtable->add_tablehead(23, T_('Start rating'), 'startRating', true);
         $gtable->add_tablehead(24, T_('End rating'), 'endRating', true);
         $gtable->add_tablehead(16, T_('Rating'), 'Rating', true);
         $gtable->add_tablehead(25, T_('Rating diff'), 'ratingDiff', true);
         $gtable->add_tablehead( 5, T_('Color'), 'Color');
      }
   }
   else //if( $running )
   {
      if( $all)
      {
         $gtable->add_tablehead(17, T_('Black name'), 'blackName');
         $gtable->add_tablehead(18, T_('Black userid'), 'blackHandle');
         $gtable->add_tablehead(26, T_('Black start rating'), 'blackStartRating', true);
         $gtable->add_tablehead(19, T_('Black rating'), 'blackRating', true);
         $gtable->add_tablehead(20, T_('White name'), 'whiteName');
         $gtable->add_tablehead(21, T_('White userid'), 'whiteHandle');
         $gtable->add_tablehead(29, T_('White start rating'), 'whiteStartRating', true);
         $gtable->add_tablehead(22, T_('White rating'), 'whiteRating', true);
      }
      else
      {
         $gtable->add_tablehead( 3, T_('Opponent'), 'Name');
         $gtable->add_tablehead( 4, T_('Userid'), 'Handle');
         $gtable->add_tablehead(23, T_('Start rating'), 'startRating', true);
         $gtable->add_tablehead(16, T_('Rating'), 'Rating', true);
         $gtable->add_tablehead( 5, T_('Colors'), 'Color');
      }
   }

   $gtable->add_tablehead( 6, T_('Size'), 'Size', true);
   $gtable->add_tablehead( 7, T_('Handicap'), 'Handicap');
   $gtable->add_tablehead( 8, T_('Komi'), 'Komi');
   $gtable->add_tablehead( 9, T_('Moves'), 'Moves', true);

   if( $observe )
   {
      $gtable->add_tablehead(14, T_('Rated'), 'Rated', true);
      $gtable->add_tablehead(13, T_('Last Move'), 'Lastchanged', true);
   }
   else if( $finished )
   {
      if( $all )
      {
         $gtable->add_tablehead(10, T_('Score'), 'Score', true);
      }
      else
      {
         $gtable->add_tablehead(10, T_('Score'), 'oScore', true);
         $gtable->add_tablehead(11, T_('Win?'), 'Win', true);
      }
      $gtable->add_tablehead(14, T_('Rated'), 'Rated', true);
      $gtable->add_tablehead(12, T_('End date'), 'Lastchanged', true);
   }
   else //if( $running )
   {
      $gtable->add_tablehead(14, T_('Rated'), 'Rated', true);
      $gtable->add_tablehead(13, T_('Last Move'), 'Lastchanged', true);
      if( !$all )
         $gtable->add_tablehead(15, T_('Opponents Last Access'), 'Lastaccess', true);
      if( !$all ) //!$observe && !$finished
         $gtable->add_tablehead(12, T_('Weekend Clock'), 'WeekendClock', true);
   }


   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS, // std-fields
      'Games.*',
      'UNIX_TIMESTAMP(Lastchanged) AS Time',
      "IF(Rated='N','N','Y') as Rated" );

   if( $observe )
   {
      $qsql->add_part( SQLP_FIELDS,
         'black.Name AS blackName', 'black.Handle AS blackHandle',
         'white.Name AS whiteName', 'white.Handle AS whiteHandle',
         'black.Rating2 AS blackRating', 'black.ID AS blackID',
         'white.Rating2 AS whiteRating', 'white.ID AS whiteID',
         'Games.Black_Start_Rating AS blackStartRating',
         'Games.White_Start_Rating AS whiteStartRating' );
      $qsql->add_part( SQLP_FROM,
         'Observers AS OB',
         'JOIN Games ON Games.ID=OB.gid',
         'JOIN Players AS white ON white.ID=White_ID',
         'JOIN Players AS black ON black.ID=Black_ID' );
      $qsql->add_part( SQLP_WHERE,
         'OB.uid=' . $player_row['ID'] );
   }
   else if( $all )
   {
      $qsql->add_part( SQLP_FIELDS,
         'black.Name AS blackName', 'black.Handle AS blackHandle',
         'white.Name AS whiteName', 'white.Handle AS whiteHandle',
         'black.Rating2 AS blackRating', 'black.ID AS blackID',
         'white.Rating2 AS whiteRating', 'white.ID AS whiteID',
         'Games.Black_Start_Rating AS blackStartRating',
         'Games.White_Start_Rating AS whiteStartRating' );
      $qsql->add_part( SQLP_FROM,
         'Games',
         'JOIN Players AS white ON white.ID=White_ID',
         'JOIN Players AS black ON black.ID=Black_ID' );

      if ( $finished )
      {
         $qsql->add_part( SQLP_FIELDS,
            'Black_End_Rating AS blackEndRating',
            'White_End_Rating AS whiteEndRating',
            'blog.RatingDiff AS blackDiff',
            'wlog.RatingDiff AS whiteDiff' );
         $qsql->add_part( SQLP_FROM,
            'LEFT JOIN Ratinglog AS blog ON blog.gid=Games.ID AND blog.uid=Black_ID',
            'LEFT JOIN Ratinglog AS wlog ON wlog.gid=Games.ID AND wlog.uid=White_ID' );
         $qsql->add_part( SQLP_WHERE, "Status='FINISHED'" );
      }
      else
         $qsql->add_part( SQLP_WHERE, "Status NOT IN ('INVITED','FINISHED')" );
   }
   else // user
   {
      $qsql->add_part( SQLP_FIELDS,
         'Name',
         'Handle',
         'Players.ID as pid',
         'Rating2 AS Rating',
         "IF(Black_ID=$uid, Games.White_Start_Rating, Games.Black_Start_Rating) AS startRating",
         'UNIX_TIMESTAMP(Lastaccess) AS Lastaccess',
         "IF(Black_ID=$uid,1,0) AS iamBlack",
         //extra bits of Color are for sorting purposes
         "IF(ToMove_ID=$uid,0,0x10)+IF(White_ID=$uid,2,0)+IF(White_ID=ToMove_ID,1,IF(Black_ID=ToMove_ID,0,0x20)) AS Color" );
      $qsql->add_part( SQLP_FROM, 'Games', 'Players' );
      if ( $finished )
      {
         $qsql->add_part( SQLP_FIELDS,
            "SIGN(IF(Black_ID=$uid, -Score, Score)) AS Win",
            "IF(Black_ID=$uid, -Score, Score) AS oScore",
            "IF(Black_ID=$uid, Games.White_End_Rating, Games.Black_End_Rating) AS endRating",
            'log.RatingDiff AS ratingDiff' );
         $qsql->add_part( SQLP_FROM, "LEFT JOIN Ratinglog AS log ON log.gid=Games.ID AND log.uid=$uid" );
         $qsql->add_part( SQLP_WHERE, "Status='FINISHED'" );
      }
      else // running
      {
         $qsql->add_part( SQLP_WHERE, "Status NOT IN ('INVITED','FINISHED')" );
      }

      $qsql->add_part( SQLP_WHERE,
         //"(( Black_ID=$uid AND White_ID=Players.ID ) OR
         //( White_ID=$uid AND Black_ID=Players.ID ))"
         "(White_ID=$uid OR Black_ID=$uid)",
         "Players.ID=White_ID+Black_ID-$uid" );

      $order .= ',Games.ID';
   }

   $qsql->merge( $gtable->get_query() );
   $query = $qsql->get_select() . " ORDER BY $order $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'show_games.find_games');

   $show_rows = $gtable->compute_show_rows(mysql_num_rows($result));

   if( $observe or $all)
   {
      $title1 = $title2 = ( $observe ? T_('Observed games') :
                            ( $finished ? T_('Finished games') : T_('Running games') ) );
   }
   else
   {
      $games_for = ( $finished ? T_('Finished games for %s') : T_('Running games for %s') );
      $title1 = sprintf( $games_for, make_html_safe($user_row["Name"]) );
      $title2 = sprintf( $games_for, user_reference( REF_LINK, 1, '', $user_row) );
   }


   start_page( $title1, true, $logged_in, $player_row,
               $gtable->button_style($player_row['Button']) );

   if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title2</h3>\n";

   // hover-texts for colors-column
   // (don't add 'w' and 'b', or else need to show in status.php too)
   $arr_titles_colors = array(
      'w_w' => T_('You have White, White to move'),
      'w_b' => T_('You have White, Black to move'),
      'b_w' => T_('You have Black, White to move'),
      'b_b' => T_('You have Black, Black to move'),
   );

   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $Rating = $blackRating = $whiteRating = NULL;
      $startRating = $blackStartRating = $whiteStartRating = NULL;
      $endRating = $blackEndRating = $whiteEndRating = NULL;
      $blackDiff = $whiteDiff = $ratingDiff = NULL;
      extract($row);

      $grow_strings = array();
      if( $gtable->Is_Column_Displayed[1] )
         $grow_strings[1] = $gtable->button_TD_anchor( "game.php?gid=$ID", $ID);
      if( $gtable->Is_Column_Displayed[2] )
         $grow_strings[2] = "<td><A href=\"sgf.php?gid=$ID\">" .
            "<font color=$sgf_color>" . T_('sgf') . "</font></A></td>";

      if( $observe or $all )
      {
         if( $gtable->Is_Column_Displayed[17] )
            $grow_strings[17] = "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
               make_html_safe($blackName) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[18] )
            $grow_strings[18] = "<td><A href=\"userinfo.php?uid=$blackID\"><font color=black>" .
               $blackHandle . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[26] )
            $grow_strings[26] = "<td>" . echo_rating($blackStartRating,true,$blackID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[27] )
            $grow_strings[27] = "<td>" . echo_rating($blackEndRating,true,$blackID) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[19] )
            $grow_strings[19] = "<td>" . echo_rating($blackRating,true,$blackID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[28] )
            $grow_strings[28] = "<td>" .
               (isset($blackDiff) ? ($blackDiff > 0 ? '+' : '') .
                sprintf("%0.2f",$blackDiff*0.01) : '&nbsp;' ) . "</td>";
         if( $gtable->Is_Column_Displayed[20] )
            $grow_strings[20] = "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
               make_html_safe($whiteName) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[21] )
            $grow_strings[21] = "<td><A href=\"userinfo.php?uid=$whiteID\"><font color=black>" .
               $whiteHandle . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[29] )
            $grow_strings[29] = "<td>" . echo_rating($whiteStartRating,true,$whiteID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[30] )
            $grow_strings[30] = "<td>" . echo_rating($whiteEndRating,true,$whiteID) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[22] )
            $grow_strings[22] = "<td>" . echo_rating($whiteRating,true,$whiteID) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[31] )
            $grow_strings[31] = "<td>" .
               (isset($whiteDiff) ? ($whiteDiff > 0 ? '+' : '') .
                sprintf("%0.2f",$whiteDiff*0.01) : '&nbsp;' ) . "</td>";
      }
      else
      {
         if( $gtable->Is_Column_Displayed[3] )
            $grow_strings[3] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               make_html_safe($Name) . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[4] )
            $grow_strings[4] = "<td><A href=\"userinfo.php?uid=$pid\"><font color=black>" .
               $Handle . "</font></a></td>";
         if( $gtable->Is_Column_Displayed[23] )
            $grow_strings[23] = "<td>" . echo_rating($startRating,true,$pid) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[24] )
            $grow_strings[24] = "<td>" . echo_rating($endRating,true,$pid) . "&nbsp;</td>";
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = "<td>" . echo_rating($Rating,true,$pid) . "&nbsp;</td>";
         if( $finished and $gtable->Is_Column_Displayed[25] )
            $grow_strings[25] = "<td>" .
               (isset($ratingDiff) ? ($ratingDiff > 0 ? '+' : '') .
                sprintf("%0.2f",$ratingDiff*0.01) : '&nbsp;' ) . "</td>";

         if( $gtable->Is_Column_Displayed[5] )
         {
            if( $Color & 2 ) //my color
               $colors = 'w';
            else
               $colors = 'b';
            if( !($Color & 0x20) )
            {
               if( $Color & 1 ) //to move color
                  $colors.= '_w';
               else
                  $colors.= '_b';
            }
            $hover_title = ( isset($arr_titles_colors[$colors]) )
               ? "title=\"" . $arr_titles_colors[$colors] . "\"" : '';
            $grow_strings[5] = "<td align=center><img src=\"17/$colors.gif\" "
               . "alt=\"$colors\" $hover_title></td>";
         }
      }

      if( $gtable->Is_Column_Displayed[6] )
         $grow_strings[6] = "<td>$Size</td>";
      if( $gtable->Is_Column_Displayed[7] )
         $grow_strings[7] = "<td>$Handicap</td>";
      if( $gtable->Is_Column_Displayed[8] )
         $grow_strings[8] = "<td>$Komi</td>";
      if( $gtable->Is_Column_Displayed[9] )
         $grow_strings[9] = "<td>$Moves</td>";

      if( $finished )
      {
         if( $gtable->Is_Column_Displayed[10] )
            $grow_strings[10] = '<td>' . score2text($Score, false) . "</td>";
         if( !$all )
         {

            if( $gtable->Is_Column_Displayed[11] )
            {
               $src = '"images/' .
                  ( $Win == 1 ? 'yes.gif" alt="' . T_('Yes') :
                     ( $Win == -1 ? 'no.gif" alt="' . T_('No') :
                        'dash.gif" alt="' . T_('jigo') )) . '"';
               $grow_strings[11] = "<td align=center><img src=$src></td>";
            }
         }
         if( $gtable->Is_Column_Displayed[14] )
            $grow_strings[14] = "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>";
         if( $gtable->Is_Column_Displayed[12] )
            $grow_strings[12] = '<td>' . date($date_fmt, $Time) . "</td>";
      }
      else //if( !$finished or $observe )
      {
         if( !$observe and !$all and $gtable->Is_Column_Displayed[12] )
            $grow_strings[12] = "<td>" . ($WeekendClock == 'Y' ? T_('Yes') : T_('No') ) . "</td>";
         if( $gtable->Is_Column_Displayed[14] )
            $grow_strings[14] = "<td>" . ($Rated == 'N' ? T_('No') : T_('Yes') ) . "</td>";
         if( $gtable->Is_Column_Displayed[13] )
            $grow_strings[13] = '<td>' . date($date_fmt, $Time) . "</td>";
         if( !$observe and !$all and $gtable->Is_Column_Displayed[15] )
            $grow_strings[15] = '<td align=center>' . date($date_fmt, $Lastaccess) . "</td>";
      }

      $gtable->add_row( $grow_strings );
   }

   $gtable->echo_table();

   $menu_array = array();

   $myID = $player_row["ID"];
   if( $observe )
      $uid = $myID;

   if( !$all )
   {
      if ( $uid != $myID )
         $menu_array[T_('User info')] = "userinfo.php?uid=$uid";

      if( $uid != $player_row["ID"] and !$observe )
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite".URI_AMP."uid=$uid";
   }

   $row_str = $gtable->current_rows_string();

   if( $finished or $observe )
      $menu_array[T_('Show running games')] = "show_games.php?uid=$uid".URI_AMP.$row_str;
   if( !$finished )
      $menu_array[T_('Show finished games')] = "show_games.php?uid=$uid".URI_AMP."finished=1".URI_AMP.$row_str;
   if( !$observe )
      $menu_array[T_('Show observed games')] = "show_games.php?observe=1".URI_AMP.$row_str;

   end_page(@$menu_array);
}
?>
