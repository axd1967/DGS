<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/countries.php" );
require_once( "include/filter.php" );


$ARR_DBFIELDKEYS = array(
   'cntGames',
   'cntJigo',
   'cntHandicap', 'maxHandicap',
   'cntWon',  'cntWonTime',  'cntWonResign',  'cntWonScore',
   'cntLost', 'cntLostTime', 'cntLostResign', 'cntLostScore'
);


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   /*
    * uid = player to show opponents for, if empty, use myself
    * opp = if unset, show user-table of players opponents to select for stats
    *       if set, show game-stats for player $uid and opponent $opp
    */

   // check vars
   $myID = $player_row['ID'];
   $uid = get_request_arg( 'uid', $myID );
   $opp = get_request_arg( 'opp' );
   if ( empty($uid) )
      $uid = $myID;
   if ( $opp and ($opp === $uid) )
      error("Opponent must be distinct from uid [$uid]");
   if ( !is_numeric($uid) )
      error("Bad uid [$uid] used");
   if ( $opp and !is_numeric($opp) )
      error("Bad opponent uid [$opp] used");

   $page = "user_stats.php?";


   // who are player (uid) and opponent (opp) ?
   $players = array(); // uid => ( Players.field => value )
   $query = "SELECT "
      . "ID,Handle,Name,Country,Rating2, "
      . "(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel, "
      . "IFNULL(UNIX_TIMESTAMP(Lastaccess),0) AS lastAccess, "
      . "IFNULL(UNIX_TIMESTAMP(LastMove),0) AS lastMove "
      . "FROM Players WHERE ID IN ('$uid','$opp')";
   $result = mysql_query( $query )
      or error('mysql_query_failed', 'user_stats.find_data_opponent');
   while ( $row = mysql_fetch_assoc( $result ) )
      $players[ $row['ID'] ] = array_merge( array(), $row ); // copy arr
   if ( !isset($players[$uid]) )
      error("Can't find user with uid [$uid]");
   if ( $opp and !isset($players[$opp]) )
      error("Can't find opponent-user with uid [$opp]");


   // static filters
   $usfilter = new SearchFilter('s');
   $usfilter->add_filter(1, 'Numeric',      'G.Size', true );
   $usfilter->add_filter(2, 'RatedSelect',  'G.Rated', true );
   $usfilter->add_filter(3, 'Date',         'G.Lastchanged', true );
   $usfilter->add_filter(4, 'Selection',
         array( T_('All games') => '',
                T_('Running games')  => "G.Status NOT IN ('INVITED','FINISHED')",
                T_('Finished games') => "G.Status='FINISHED'" ),
         true,
         array( FC_DEFAULT => 2 ) );
   $usfilter->init();
   $f_status =& $usfilter->get_filter(4);

   // table filters
   $ufilter = new SearchFilter();
   $ufilter->add_filter( 1, 'Numeric', 'P.ID', true);
   $ufilter->add_filter( 2, 'Text',    'P.Name', true,
         array( FC_SIZE => 12 ));
   $ufilter->add_filter( 3, 'Text',    'P.Handle', true,
         array( FC_SIZE => 12 ));
   $ufilter->add_filter( 4, 'Text',    'P.Rank', true); # Rank info
   $ufilter->add_filter( 5, 'Rating',  'P.Rating2', true);
   $ufilter->add_filter( 6, 'Text',    'P.Open', true); # Open for matches
   $ufilter->add_filter( 7, 'Numeric', 'Games', true,   # =P.Running+P.Finished
         array( FC_ADD_HAVING => 1 ));
   $ufilter->add_filter( 8, 'Numeric', 'P.Running', true);
   $ufilter->add_filter( 9, 'Numeric', 'P.Finished', true);
   $ufilter->add_filter(10, 'Numeric', 'P.Won', true);
   $ufilter->add_filter(11, 'Numeric', 'P.Lost', true);
   $ufilter->add_filter(13, 'Boolean', "P.Activity>$ActiveLevel1", true,
         array( FC_FNAME => 'active', FC_LABEL => T_('Active'), FC_STATIC => 1 ) );
   $ufilter->add_filter(14, 'RelativeDate', 'P.Lastaccess', true);
   $ufilter->add_filter(15, 'RelativeDate', 'P.Lastmove', true,
         array( FC_TIME_UNITS => FRDTU_DHM ));
   $ufilter->add_filter(16, 'Country', 'P.Country', false);
   $ufilter->add_filter(17, 'Numeric', 'P.RatedGames', true);
   $ufilter->init(); // parse current value from _GET

   $utable = new Table( 'user', $page, 'UsersColumns' );
   $utable->set_default_sort( 'P.ID', 0);
   $utable->register_filter( $ufilter );
   $utable->add_or_del_column();

   // External-Form
   $usform = new Form( $utable->Prefix, $page, FORM_GET, false, 'formTable');
   $usform->set_layout( FLAYOUT_GLOBAL, ( $opp ? '2,(1|4|3)' : '2' ) );
   $usform->set_layout( FLAYOUT_AREACONF, FAREA_ALL,
      array( FAC_ENVTABLE => 'align=center' ) );
   $usform->set_attr_form_element( 'Description', FEA_ALIGN, 'left' );
   $usform->set_config( FEC_EXTERNAL_FORM, true );
   $utable->set_externalform( $usform ); // also attach offset, sort, manage-filter as hidden (table) to ext-form
   $usform->attach_table( $usfilter ); // attach manage-filter as hiddens (static) to ext-form

   // page-vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'uid', $uid );
   if ( $opp )
      $page_vars->add_entry( 'opp', $opp );
   $usform->attach_table( $page_vars ); // for page­vars as hiddens in form

   // attach external URL-parameters from static filter and page-vars for table-links
   $utable->add_external_parameters( $usfilter->get_req_params() );
   $utable->add_external_parameters( $page_vars );

   // add_tablehead($nr, $descr, $sort=NULL, $desc_def=false, $undeletable=false, $attbs=NULL)
   $utable->add_tablehead( 1, T_('Opponent ID'), 'P.ID', false, true);
   $utable->add_tablehead( 2, T_('Name'), 'P.Name');
   $utable->add_tablehead( 3, T_('Userid'), 'P.Handle');
   $utable->add_tablehead(16, T_('Country'), 'P.Country');
   $utable->add_tablehead( 4, T_('Rank info'));
   $utable->add_tablehead( 5, T_('Rating'), 'P.Rating2', true);
   $utable->add_tablehead( 6, T_('Open for matches?'));
   $utable->add_tablehead( 7, T_('Games'), 'Games', true);
   $utable->add_tablehead( 8, T_('Running'), 'P.Running', true);
   $utable->add_tablehead( 9, T_('Finished'), 'P.Finished', true);
   $utable->add_tablehead(17, T_('Rated'), 'P.RatedGames', true);
   $utable->add_tablehead(10, T_('Won'), 'P.Won', true);
   $utable->add_tablehead(11, T_('Lost'), 'P.Lost', true);
   $utable->add_tablehead(12, T_('Percent'), 'Percent', true);
   $utable->add_tablehead(13, T_('Activity'), 'ActivityLevel', true, true);
   $utable->add_tablehead(14, T_('Last access'), 'P.Lastaccess', true);
   $utable->add_tablehead(15, T_('Last Moved'), 'P.Lastmove', true);


   // form for static filters
   if ( $opp )
   {
      $usform->set_area( 1 );
      $usform->add_row( array(
            'OWNHTML', print_players_table( $players, $uid, $opp ) ));
      $usform->add_empty_row();

      $usform->set_area( 4 );
      $usform->add_row( array( 'TEXT', '&nbsp;&nbsp;&nbsp;' ));
   }

   $usform->set_area( 2 );
   $usform->add_row( array(
         'CHAPTER',     T_('Search-parameters on games with opponent') ));
   $usform->add_row( array(
         'DESCRIPTION', T_('Size'),
         'FILTER',      $usfilter, 1,
         'FILTERERROR', $usfilter, 1, "<br>$FERR1", $FERR2, true ));
   $usform->add_row( array(
         'DESCRIPTION', T_('Rated'),
         'FILTER',      $usfilter, 2 ));
   $usform->add_row( array(
         'DESCRIPTION', T_('Status'),
         'FILTER',      $usfilter, 4,
         'TEXT',        ' (' . T_('full stats only for finished games') . ')' ));
   $usform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'FILTER',      $usfilter, 3,
         'FILTERERROR', $usfilter, 3, "<br>$FERR1", $FERR2, true ));
   $usform->add_row( array(
         'TAB',
         'CELL',        1, 'align=left',
         'OWNHTML',     implode( '', $ufilter->get_submit_elements() ), ));
   $usform->add_empty_row();


   // build SQL-query (for user-table)
   $query_usfilter = $usfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $query_ufilter  = $utable->get_query(); // clause-parts for filter
   $finished = ( $f_status->get_value() == 2);

   $order = $utable->current_order_string();
   $limit = $utable->current_limit_string();

   $uqsql = new QuerySQL( // base-query is to show only opponents
      SQLP_OPTS, 'DISTINCT',
      SQLP_FROM, 'Games G',
      SQLP_WHERE, "(G.White_ID=$uid OR G.Black_ID=$uid)", "P.ID=G.White_ID+G.Black_ID-$uid" );
   $uqsql->add_part( SQLP_FIELDS,
      'P.*', 'P.Rank AS Rankinfo',
      "(P.Activity>$ActiveLevel1)+(P.Activity>$ActiveLevel2) AS ActivityLevel",
      'P.Running+P.Finished AS Games',
      "ROUND(100*P.Won/P.RatedGames) AS Percent",
      'IFNULL(UNIX_TIMESTAMP(P.Lastaccess),0) AS lastaccess',
      'IFNULL(UNIX_TIMESTAMP(P.LastMove),0) AS Lastmove' );
   $uqsql->add_part( SQLP_FROM, 'Players P' );
   $uqsql->merge( $query_usfilter );
   $uqsql->merge( $query_ufilter );
   $query = $uqsql->get_select() . " ORDER BY $order $limit";


   // build SQL-query (for stats-fields)
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'COUNT(*) as cntGames',
      'SUM(IF(G.Handicap>0,1,0)) as cntHandicap',
      'MAX(G.Handicap) as maxHandicap',
      'SUM(IF(G.Score=0,1,0)) as cntJigo' );
   $qsql->add_part( SQLP_FROM, 'Games G' );
   $qsql->merge ( $query_usfilter ); // clause-parts for filter

   if ( $opp )
   {
      // uid is black
      $qsql_black = new QuerySQL( SQLP_WHERE, "G.Black_ID=$uid", "G.White_ID=$opp" );
      $qsql_black->add_part( SQLP_FIELDS,
         // won
         'SUM(IF(G.Score<0,1,0)) as cntWon',
         'SUM(IF(G.Score='.-SCORE_TIME.',1,0)) as cntWonTime',
         'SUM(IF(G.Score='.-SCORE_RESIGN.',1,0)) as cntWonResign',
         'SUM(IF(G.Score>='.-SCORE_MAX.' AND G.Score<0,1,0)) as cntWonScore',
         // lost
         'SUM(IF(G.Score>0,1,0)) as cntLost',
         'SUM(IF(G.Score='.SCORE_TIME.',1,0)) as cntLostTime',
         'SUM(IF(G.Score='.SCORE_RESIGN.',1,0)) as cntLostResign',
         'SUM(IF(G.Score>0 AND G.Score<='.SCORE_MAX.',1,0)) as cntLostScore' );
      $qsql_black->merge( $qsql );

      // uid is white
      $qsql_white = new QuerySQL( SQLP_WHERE, "G.White_ID=$uid", "G.Black_ID=$opp" );
      $qsql_white->add_part( SQLP_FIELDS,
         // won
         'SUM(IF(G.Score>0,1,0)) as cntWon',
         'SUM(IF(G.Score='.SCORE_TIME.',1,0)) as cntWonTime',
         'SUM(IF(G.Score='.SCORE_RESIGN.',1,0)) as cntWonResign',
         'SUM(IF(G.Score>0 AND G.Score<='.SCORE_MAX.',1,0)) as cntWonScore',
         // lost
         'SUM(IF(G.Score<0,1,0)) as cntLost',
         'SUM(IF(G.Score='.-SCORE_TIME.',1,0)) as cntLostTime',
         'SUM(IF(G.Score='.-SCORE_RESIGN.',1,0)) as cntLostResign',
         'SUM(IF(G.Score>='.-SCORE_MAX.' AND G.Score<0,1,0)) as cntLostScore' );
      $qsql_white->merge( $qsql );

      // query database for user-stats for black & white (2 queries)
      $query_black = $qsql_black->get_select();
      $query_white = $qsql_white->get_select();
      $AB = extract_user_stats( 'black', $query_black );
      $AW = extract_user_stats( 'white', $query_white );
   }
   else
   {
      // dummy fill with 0-vals
      $AB = extract_user_stats( 'black' );
      $AW = extract_user_stats( 'white' );
   }

   // query database for user-table
   $result = mysql_query( $query )
      or error('mysql_query_failed', 'user_stats.find_data');

   $show_rows = $utable->compute_show_rows(mysql_num_rows($result));

   if ( $opp )
   {
      // add stats-table into form (0-value-table if no opp)
      $usform->set_area( 3 );
      $usform->add_row( array(
            'OWNHTML', print_stats_table( $players, $AB, $AW, $uid, $opp, $finished ) ));
   }


   $stats_for = T_('Game statistics for player %s');
   $opp_for   = T_('Opponents of player %s');
   $title1 = sprintf( $stats_for, make_html_safe( $players[$uid]['Name']) );
   $title2 = sprintf( $stats_for, user_reference( REF_LINK, 1, '', $players[$uid]) );
   $title3 = sprintf( $opp_for,   user_reference( REF_LINK, 1, '', $players[$uid]) );

   start_page( $title1, true, $logged_in, $player_row );
   if ( $DEBUG_SQL and isset($query_black) ) echo "QUERY-BLACK: " . make_html_safe($query_black) . "<br>\n";
   if ( $DEBUG_SQL and isset($query_white) ) echo "QUERY-WHITE: " . make_html_safe($query_white) . "<br>\n";
   if ( $DEBUG_SQL and !$opp) echo "QUERY: " . make_html_safe($query) . "<br>\n";

   // static filter-values
   $arrtmp = array();
   $filterURL = $usfilter->get_url_parts( $arrtmp );
   if ( $filterURL )
      $filterURL .= URI_AMP;

   // build user-table
   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $ID = $row['ID'];

      $urow_strings = array();
      if( $utable->Is_Column_Displayed[1] )
         $urow_strings[1] = "<td><A href=\"{$page}{$filterURL}uid=$uid".URI_AMP."opp=$ID\">$ID</A></td>";
      if( $utable->Is_Column_Displayed[2] )
         $urow_strings[2] = "<td><A href=\"userinfo.php?uid=$ID\">" .
            make_html_safe($row['Name']) . "</A></td>";
      if( $utable->Is_Column_Displayed[3] )
         $urow_strings[3] = "<td><A href=\"userinfo.php?uid=$ID\">" .
            $row['Handle'] . "</A></td>";
      if( $utable->Is_Column_Displayed[16] )
      {
         $cntr = @$row['Country'];
         $cntrn = T_(@$COUNTRIES[$cntr]);
         $cntrn = (empty($cntr) ? '' :
             "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">");
         $urow_strings[16] = "<td>" . $cntrn . "</td>";
      }
      if( $utable->Is_Column_Displayed[4] )
         $urow_strings[4] = '<td>' . make_html_safe(@$row['Rankinfo'],INFO_HTML) . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[5] )
         $urow_strings[5] = '<td>' . echo_rating(@$row['Rating2'],true,$ID) . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[6] )
         $urow_strings[6] = '<td>' . make_html_safe($row['Open'],INFO_HTML) . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[7] )
         $urow_strings[7] = '<td>' . $row['Games'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[8] )
         $urow_strings[8] = '<td>' . $row['Running'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[9] )
         $urow_strings[9] = '<td>' . $row['Finished'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[17] )
         $urow_strings[17] = '<td>' . $row['RatedGames'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[10] )
         $urow_strings[10] = '<td>' . $row['Won'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[11] )
         $urow_strings[11] = '<td>' . $row['Lost'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[12] )
      {
         $percent = ( is_numeric($row['Percent']) ? $row['Percent'].'%' : '' );
         $urow_strings[12] = '<td>' . $percent . '&nbsp;</td>';
      }
      if( $utable->Is_Column_Displayed[13] )
      {
         $activity = activity_string( $row['ActivityLevel']);
         $urow_strings[13] = '<td>' . $activity . '&nbsp;</td>';
      }
      if( $utable->Is_Column_Displayed[14] )
      {
         $lastaccess = ($row["lastaccess"] > 0 ? date($date_fmt2, $row["lastaccess"]) : NULL );
         $urow_strings[14] = '<td>' . $lastaccess . '&nbsp;</td>';
      }
      if( $utable->Is_Column_Displayed[15] )
      {
         $lastmove = ($row["Lastmove"] > 0 ? date($date_fmt2, $row["Lastmove"]) : NULL );
         $urow_strings[15] = '<td>' . $lastmove . '&nbsp;</td>';
      }

      $utable->add_row( $urow_strings );
   }

   // print form with user-table
   if ( $opp ) // has opp
   {
      // print static-filter, player-info, stats-table
      echo "<center>\n"
         . $usform->print_start_default()
         . "<br>\n"
         . "<center><h3><font color=$h3_color>$title2</font></h3></center>\n"
         . $usform->get_form_string() // static form
         . $usform->print_end()
         . "</center>\n";
   }
   else // no opp
   {
      // print static-filter, user-table
      echo "<center><h3><font color=$h3_color>$title3</font></h3></center>\n"
         . "<center>\n"
         . $usform->print_start_default()
         . $usform->get_form_string() // static form
         . $utable->make_table()
         . $usform->print_end()
         . "</center>\n";
   }

   // end of table

   $menu_array = array();
   if ( $opp )
      $menu_array[ T_('Back to opponents') ] = "{$page}{$filterURL}uid=$uid";

   if ( $uid !== $myID ) // others opponents
   {
      $menu_array[ T_('Show my opponents') ]   = "{$page}{$filterURL}";
      $menu_array[ T_('Show me as opponent') ] = "{$page}{$filterURL}uid=$uid".URI_AMP."opp=$myID";
      $menu_array[ T_('Show as my opponent') ] = "{$page}{$filterURL}opp=$uid";
   }

   if ( $opp )
      $menu_array[ T_('Switch opponent role') ] = "{$page}{$filterURL}uid=$opp".URI_AMP."opp=$uid";

   end_page(@$menu_array);
}


// return array with dbfields extracted from passed db-result
// keys: cntGames, cntJigo, (cnt|max)Handicap, cnt(Won|Lost)(|Time|Resign|Score)
// param query: if null, only fill array with 0-values
function extract_user_stats( $color, $query = null )
{
   global $ARR_DBFIELDKEYS;

   $arr = array();
   if ( !is_null($query) )
   {
      $result = mysql_query( $query )
         or error('mysql_query_failed', 'user_stats.find_data_'.$color);

      if ( $row = mysql_fetch_assoc( $result ) )
      {
         foreach( $ARR_DBFIELDKEYS as $key )
            $arr[$key] = $row[$key];
      }
   }

   foreach( $ARR_DBFIELDKEYS as $key )
   {
      if ( @$arr[$key] == '' )
         $arr[$key] = 0;
   }

   return $arr;
}

// echo table with info about both players: uid and opponent
// param p: players-array[$uid,$opp] = ( ID, Name, Handle, Rating2, Country )
// param opp: maybe 0|empty
function print_players_table( $p, $uid, $opp )
{
   global $COUNTRIES, $date_fmt2;

   $p1 = $p[$uid];
   $p2 = ( $opp and isset($p[$opp]) ) ? $p[$opp] : null;
   $SPC = '&nbsp;';

   $rowpatt = "  <tr> <td><b>%s</b></td>  <td>%s</td>  <td>%s</td>  </tr>\n";

   $r = "<table id=userInfos class=Infos>\n";
   $r .= "<colgroup><col class=ColRubric><col class=ColInfo><col class=ColInfo></colgroup>\n";

   // header
   $r .= "  <tr>\n";
   $r .= "    <th>&nbsp;</th>\n";
   $r .= "    <th>Player</th>\n";
   $r .= "    <th>Opponent</th>\n";
   $r .= "  </tr>\n";

   // Name, Handle
   $r .= sprintf( $rowpatt, T_('Name'),
      "<A href=\"userinfo.php?uid=$uid\">" . make_html_safe( $p1['Name']) . "</A>",
      ( $p2 ? "<A href=\"userinfo.php?uid=$opp\">" . make_html_safe( $p2['Name']) . "</A>" : '---' ) );
   $r .= sprintf( $rowpatt, T_('Userid'), $p1['Handle'], ($p2 ? $p2['Handle'] : $SPC) );

   // Country
   $c1 = $p1['Country'];
   $c2 = ($p2 ? $p2['Country'] : '');
   $cn1 = T_(@$COUNTRIES[$c1]);
   $cn2 = T_(@$COUNTRIES[$c2]);
   $c1 = (empty($c1) ? '' : "<img title=\"$cn1\" alt=\"$cn1\" src=\"images/flags/$c1.gif\">");
   $c2 = (empty($c2) ? '' : "<img title=\"$cn2\" alt=\"$cn2\" src=\"images/flags/$c2.gif\">");
   $r .= sprintf( $rowpatt, T_('Country'), $c1, ( $p2 ? $c2 : $SPC ) );

   // Activity
   $r .= sprintf( $rowpatt, T_('Activity'),
         activity_string( $p1['ActivityLevel'] ) . '&nbsp;',
         ( $p2 ? activity_string( $p2['ActivityLevel'] ) . $SPC : $SPC ) );

   // Rating2
   $r .= sprintf( $rowpatt, T_('Rating'),
      echo_rating( $p1['Rating2'], true, $uid ),
      ( $p2 ? echo_rating( $p2['Rating2'], true, $opp ) : $SPC ) );

   // Last accessed, Last moved
   $r .= sprintf( $rowpatt, T_('Last access'),
      ($p1['lastAccess'] > 0 ? date($date_fmt2, $p1['lastAccess']) : $SPC ),
      ( $p2 && $p2['lastAccess'] > 0 ? date($date_fmt2, $p2['lastAccess']) : $SPC ) );
   $r .= sprintf( $rowpatt, T_('Last Moved'),
      ($p1['lastMove'] > 0 ? date($date_fmt2, $p1['lastMove']) : $SPC ),
      ( $p2 && $p2['lastMove'] > 0 ? date($date_fmt2, $p2['lastMove']) : $SPC ) );

   $r .= '</table>';
   return $r;
}

// echo table with info about both players: uid and opponent
// param p: players-array[$uid,$opp] = ( ID, Name, Handle, Rating2, Country )
// param B|W: array with $ARR_DBFIELDKEYS for black and white
function print_stats_table( $p, $B, $W, $uid, $opp, $fin )
{
   $p1 = $p[$uid];
   #$p2 = $p[$opp]; // $opp maybe not set or not existing
   $rowpatt   = "  <tr> <td nowrap=\"1\"><b>%s</b></td>  <td colspan=2>%s</td>  <td colspan=2>%s</td>  <td colspan=2>%s</td>  </tr>\n";
   $rowpatt2  = "  <tr> <td nowrap=\"1\"><b>%s</b></td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  </tr>\n";

   $r = "<table id=userInfos class=Infos>\n";
   $r .= "<colgroup><col class=ColRubric><col class=ColInfo><col class=ColInfo></colgroup>\n";

   // header
   $r .= "  <tr>\n";
   $r .= "    <th>" . T_('Players color') .":</th>\n";
   $r .= "    <th colspan=2><img src=\"17/b.gif\" alt=\"as-black\"></th>\n";
   $r .= "    <th colspan=2><img src=\"17/w.gif\" alt=\"as-white\"></th>\n";
   $r .= "    <th colspan=2>" . T_('Sum') . "</th>\n";
   $r .= "  </tr>\n";

   $cnt_games  = $B['cntGames'] + $W['cntGames'];
   $won_games  = $B['cntWon'] + $W['cntWon'];
   $jigo_games = $B['cntJigo'] + $W['cntJigo'];

   if ( $fin )
   {
      // stats: win/lost-ratio
      $ratio = ( $cnt_games == 0) ? 0 : round( 100 * ( $won_games + $jigo_games/2 ) / $cnt_games );
      $ratio_black = ( $B['cntGames'] == 0) ? 0 : round( 100 * ( $B['cntWon'] + $B['cntJigo']/2 ) / $B['cntGames'] );
      $ratio_white = ( $W['cntGames'] == 0) ? 0 : round( 100 * ( $W['cntWon'] + $W['cntJigo']/2 ) / $W['cntGames'] );
      $r .= sprintf( $rowpatt, T_('Win-Ratio on #Games'), $ratio_black.'%', $ratio_white.'%', $ratio.'%' );
   }

   // stats: total games
   $r .= sprintf( $rowpatt, T_('#Games'), $B['cntGames'], $W['cntGames'], $cnt_games );

   if ( $fin )
   {
      // stats: won + lost games
      $r .= sprintf( $rowpatt2, T_('#Games won : lost'),
         $B['cntWon'], $B['cntLost'],
         $W['cntWon'], $W['cntLost'],
         ($won_games), ($B['cntLost'] + $W['cntLost']) );
      $r .= sprintf( $rowpatt2, T_('#Games won : lost by Score'),
         $B['cntWonScore'], $B['cntLostScore'],
         $W['cntWonScore'], $W['cntLostScore'],
         ($B['cntWonScore'] + $W['cntWonScore']), ($B['cntLostScore'] + $W['cntLostScore']) );
      $r .= sprintf( $rowpatt2, T_('#Games won : lost by Resignation'),
         $B['cntWonResign'], $B['cntLostResign'],
         $W['cntWonResign'], $W['cntLostResign'],
         ($B['cntWonResign'] + $W['cntWonResign']), ($B['cntLostResign'] + $W['cntLostResign']) );
      $r .= sprintf( $rowpatt2, T_('#Games won : lost by Time'),
         $B['cntWonTime'], $B['cntLostTime'],
         $W['cntWonTime'], $W['cntLostTime'],
         ($B['cntWonTime'] + $W['cntWonTime']), ($B['cntLostTime'] + $W['cntLostTime']) );

      // stats: jigo
      $r .= sprintf( $rowpatt, T_('#Games with Jigo'), $B['cntJigo'], $W['cntJigo'],
         $B['cntJigo'] + $W['cntJigo']);
   }

   // stats: handicap-games
   $r .= sprintf( $rowpatt, T_('#Games with Handicap'), $B['cntHandicap'], $W['cntHandicap'],
      $B['cntHandicap'] + $W['cntHandicap']);
   $r .= sprintf( $rowpatt, T_('Maximum Handicap'), $B['maxHandicap'], $W['maxHandicap'],
      max($B['maxHandicap'], $W['maxHandicap']) );

   $r .= '</table>';
   return $r;
}

?>
