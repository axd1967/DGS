<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Tournament";

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_gui_helper.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentManage');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_TDIR_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.manage_tournament');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.manage_tournament');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.manage_tournament');

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.manage_tournament.find_tournament', $tid );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);

   // create/edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   if ( !TournamentHelper::allow_edit_tournaments($tourney, $my_id) )
      error('tournament_edit_not_allowed', "manage_tournament.edit_tournament($tid,$my_id)");
   $allow_new_del_TD = $tourney->allow_edit_directors($my_id);

   // init
   $page = "manage_tournament.php";
   $title = T_('Tournament Manager');
   $is_league = ( $tourney->Type == TOURNEY_TYPE_LEAGUE );


   // ---------- Tournament Info -----------------------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Owner#tourney'),
         'TEXT',        ( ($tourney->Owner_ID) ? user_reference( REF_LINK, 1, '', $tourney->Owner_ID ) : NO_VALUE ) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'TEXT',        TournamentUtils::buildLastchangedBy($tourney->Lastchanged, $tourney->ChangedBy) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Your Roles#tourney'),
         'TEXT', TournamentGuiHelper::getTournamentRoleText($tourney, $my_id), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Flags'),
         'TEXT', $tourney->formatFlags(NO_VALUE) . SEP_SPACING .
                 make_menu_link( T_('Edit locks#tourney'),
                     array( 'url' => "tournaments/edit_lock.php?tid=$tid", 'class' => 'TAdmin' )) ));
   if ( $tourney->LockNote )
      $tform->add_row( array(
            'DESCRIPTION', T_('Lock Note'),
            'TEXT',        make_html_safe($tourney->LockNote, true), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT', $tourney->getStatusText($tourney->Status) . SEP_SPACING .
                 make_menu_link( T_('Change Status'),
                     array( 'url' => "tournaments/edit_status.php?tid=$tid", 'class' => 'TAdmin' )) ));

   $round = $tourney->CurrentRound;
   $tround = null;
   if ( $ttype->need_rounds )
   {
      $tround = TournamentCache::load_cache_tournament_round( 'manage_tournament', $tid, $round );

      $tform->add_row( array(
            'DESCRIPTION', T_('Round Status#tourney'),
            'TEXT', TournamentRound::getStatusText($tround->Status) . SEP_SPACING .
                    make_menu_link( T_('Change Round Status#tourney'),
                        array( 'url' => "tournaments/roundrobin/edit_round_status.php?tid=$tid".URI_AMP."round=$round",
                               'class' => 'TAdmin' )) ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Rounds#tourney'),
            'TEXT', $tourney->formatRound(), ));
   }
   if ( $is_league )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Tournament Links'),
            'TEXT', TournamentGuiHelper::build_tournament_links($tourney, 'tournaments/manage_tournament.php'), ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   echo '<table id="TournamentManager"><tr><td>', "<hr>\n",
      make_header( 1, T_('Setup phase#tourney'), TOURNEY_STATUS_NEW ), //------------------------
      '<ul class="TAdminLinks">',
         '<li>', make_menu_link( T_('Edit tournament'), array( 'url' => "tournaments/edit_tournament.php?tid=$tid", 'class' => 'TAdmin' )),
                 subList( array( T_('Change start-time, title, description#t_mng') . ($is_admin ? '; ' . T_('owner, scope#t_mng') : '') )),
         '<li>', make_menu_link( T_('Add news#tnews'), array( 'url' => "tournaments/edit_news.php?tid=$tid", 'class' => 'TAdmin' )),
                 SEP_SPACING,
                 make_menu_link( T_('Show tournament news'), "tournaments/list_news.php?tid=$tid" ),
                 SEP_SPACING,
                 make_menu_link( T_('New bulletin'), "edit_bulletin.php?n_tid=$tid" ),
                 ( Bulletin::is_bulletin_admin()
                     ? SEP_SPACING . make_menu_link( T_('New admin bulletin'), array( 'url' => "admin_bulletin.php?n_tid=$tid", 'class' => 'AdminLink' ))
                     : '' ),
         '<li>', ( $allow_new_del_TD
                     ? make_menu_link( T_('Add tournament director'), array( 'url' => "tournaments/edit_director.php?tid=$tid", 'class' => 'TAdmin' ))
                     : T_('Add tournament director') ),
                 sprintf( ' (%s)', T_('only by owner#tourney') ), SEP_SPACING,
                 make_menu_link( T_('Show tournament directors'), "tournaments/list_directors.php?tid=$tid" ),
         '<li>', make_menu_link( T_('Edit registration properties#tourney'), array( 'url' => "tournaments/edit_properties.php?tid=$tid", 'class' => 'TAdmin' )),
                 subList( array( T_('tournament-related: end-time, min./max. participants, rating-use-mode, start-round#t_mng'),
                                 T_('user-related: user rating-range, min. games#t_mng') )),
         '<li>', make_menu_link( T_('Edit rules#tourney'), array( 'url' => "tournaments/edit_rules.php?tid=$tid", 'class' => 'TAdmin' )),
                 subList( array( T_('Change game-settings: shape, ruleset, board size, handicap-settings, time-settings, rated#t_mng') )),
         make_links_ttype_specific( $tourney, TOURNEY_STATUS_NEW ),
      '</ul>',

      make_header( 2, T_('Registration phase#tourney'), TOURNEY_STATUS_REGISTER ), //------------------------
      '<ul class="TAdminLinks">',
         '<li>', make_menu_link( T_('Edit participants'), array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' )),
                 SEP_SPACING,
                 make_menu_link( T_('Show tournament participants'), "tournaments/list_participants.php?tid=$tid" ),
                 ( $tourney->Rounds > 1 )
                     ? ' (' . make_menu_link( T_('current round only#tourney'),
                              "tournaments/list_participants.php?tid=$tid".URI_AMP."round=".$tourney->CurrentRound.REQF_URL.'round' ) . ')'
                     : '',
                 subList( array( T_('Manage registration of users: invite user, approve or reject application, remove registration#t_mng'),
                                 T_('Change status, start-round, read message from user and answer with message#t_mng') )),
      '</ul>',

      make_header( 3, T_('Start phase#tourney'), TOURNEY_STATUS_PAIR ), //------------------------
      '<ul class="TAdminLinks">',
         make_links_ttype_specific( $tourney, TOURNEY_STATUS_PAIR ),
      '</ul>',

      make_header( 4, T_('Play phase#tourney'), TOURNEY_STATUS_PLAY ), //------------------------
      '<ul class="TAdminLinks">',
         make_links_ttype_specific( $tourney, TOURNEY_STATUS_PLAY ),
         '<li>', make_menu_link( T_('Edit results#tourney'), array( 'url' => "tournaments/edit_results.php?tid=$tid", 'class' => 'TAdmin' )),
                 SEP_SPACING,
                 make_menu_link( T_('Show all tournament results'), "tournaments/list_results.php?tid=$tid" ),
                 subList( array( T_('Add, edit, delete tournament results'), )),
         ( $is_league )
            ? '<li>'
                  . make_menu_link( T_('Manage linked tournaments#t_mng'), array( 'url' => "tournaments/league/manage_linked.php?tid=$tid", 'class' => 'TAdmin' ))
                  . subList( array( T_('Spawn next cycle#t_mng') ))
            : '',
      '</ul>',

      '</tr></td></table>',
      "\n";


   $menu_array = array();
   if ( $tid )
      $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Show Tournament Log')] =
      array( 'url' => "tournaments/show_tournament_log.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


function make_header( $no, $title, $t_status )
{
   return sprintf( "<h4 class=\"SubHeader\">%s. %s (%s)</h4>\n",
                   $no, $title, Tournament::getStatusText($t_status) );
}

function make_links_ttype_specific( $tourney, $tstat )
{
   $tid = $tourney->ID;

   // TYPE: ladder-specific stuff
   if ( $tourney->Type == TOURNEY_TYPE_LADDER )
   {
      if ( $tstat == TOURNEY_STATUS_NEW )
         return '<li>'
            . make_menu_link( T_('Edit Ladder Properties'), array( 'url' => "tournaments/ladder/edit_props.php?tid=$tid", 'class' => 'TAdmin' ))
            . subList( array( T_('challenge-range, max. defenses, max. challenges#t_mng'),
                              T_('game-end-handling, timeout-handling, user-join-order, user-absence-handling, achievements#t_mng'), ));

      if ( $tstat == TOURNEY_STATUS_PAIR )
         return '<li>'
            . make_menu_link( T_('Admin Ladder'), array( 'url' => "tournaments/ladder/admin.php?tid=$tid", 'class' => 'TAdmin' ))
            . SEP_SPACING
            . make_menu_link( T_('Edit Ladder'), array( 'url' => "tournaments/ladder/view.php?tid=$tid".URI_AMP."admin=1", 'class' => 'TAdmin' ))
            . SEP_SPACING
            . make_menu_link( T_('View Ladder'), "tournaments/ladder/view.php?tid=$tid" )
            . subList( array( T_('Admin Ladder (seed ladder, remove users)#t_mng'),
                              T_('Edit Ladder (remove users, rank-changes)#t_mng') ));

      if ( $tstat == TOURNEY_STATUS_PLAY )
         return '<li>'
            . make_menu_link( T_('View Ladder'), "tournaments/ladder/view.php?tid=$tid" )
            . SEP_SPACING
            . make_menu_link( T_('Show all running tournament games'), "show_games.php?tid=$tid".URI_AMP."uid=all" )
            . '<li>'
            . make_admin_tgame( $tid ) . MED_SPACING . '(' . T_('also see game info pages') . ')'
            . subList( array( T_('End game, Add time#t_mng') ));
   }// ladder


   // TYPE: round-robin- & league-specific stuff
   $is_league = ( $tourney->Type == TOURNEY_TYPE_LEAGUE );
   if ( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN || $is_league )
   {
      if ( $tstat == TOURNEY_STATUS_NEW )
         return '<li>'
            . make_menu_link( T_('Edit points#tourney'), array( 'url' => "tournaments/roundrobin/edit_points.php?tid=$tid", 'class' => 'TAdmin' ))
            . subList( array( T_('Setup tournament points to determine pool-ranks') ))
            . '<li>'
            . ( $is_league
                  ? make_menu_link( T_('Edit Round Properties#tourney'), array( 'url' => "tournaments/roundrobin/edit_round_props.php?tid=$tid", 'class' => 'TAdmin' ))
                  : make_menu_link( T_('Edit rounds'), array( 'url' => "tournaments/roundrobin/edit_rounds.php?tid=$tid", 'class' => 'TAdmin' ))
              )
            . subList( array( T_('Setup tournament rounds for pooling and pairing') ));

      if ( $tstat == TOURNEY_STATUS_PAIR )
         return '<li>'
            . make_menu_link( T_('Define pools'), array( 'url' => "tournaments/roundrobin/define_pools.php?tid=$tid", 'class' => 'TAdmin' ))
            . SEP_SPACING
            . make_menu_link( T_('Create pools'), array( 'url' => "tournaments/roundrobin/create_pools.php?tid=$tid", 'class' => 'TAdmin' ))
            . SEP_SPACING
            . make_menu_link( T_('Edit pools'), array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' ))
            . SEP_SPACING
            . make_menu_link( T_('View Pools'), "tournaments/roundrobin/view_pools.php?tid=$tid" )
            . subList( array( T_('Define pools (set pool parameters: pool-size, pool-count)#t_mng'),
                              T_('Create pools (create, remove, seed pools)#t_mng'),
                              T_('Edit pools (assign users to pools)#t_mng') ))
            . '<li>'
            . make_menu_link( T_('Edit game pairing'), array( 'url' => "tournaments/roundrobin/edit_pairing.php?tid=$tid", 'class' => 'TAdmin' ))
            . subList( array( T_('Starting games for all pools#t_mng') ));

      if ( $tstat == TOURNEY_STATUS_PLAY )
         return '<li>'
            . make_menu_link( T_('Edit-View Pools'), "tournaments/roundrobin/view_pools.php?tid=$tid".URI_AMP."edit=1" )
            . SEP_SPACING
            . make_menu_link( T_('Show all running tournament games'), "show_games.php?tid=$tid".URI_AMP."uid=all" )
            . '<li>'
            . make_admin_tgame( $tid ) . MED_SPACING . '(' . T_('also see game info pages') . ')'
            . subList( array( T_('End game, Add time#t_mng') ))
            . '<li>'
            . ( $is_league
                  ? make_menu_link( T_('Edit ranks & relegations#tpool'), array( 'url' => "tournaments/roundrobin/edit_ranks.php?tid=$tid", 'class' => 'TAdmin' ))
                     . subList( array( T_('Set pool-ranks, relegations (next-cycle flagging)#t_mng') ))
                  : make_menu_link( T_('Edit ranks#tpool'), array( 'url' => "tournaments/roundrobin/edit_ranks.php?tid=$tid", 'class' => 'TAdmin' ))
                     . subList( array( T_('Set pool-ranks, pool-winners (next-round flagging)#t_mng') ))
              )
            . ( $is_league
                  ? ''
                  : '<li>'
                     . make_menu_link( T_('Edit rounds'), array( 'url' => "tournaments/roundrobin/edit_rounds.php?tid=$tid", 'class' => 'TAdmin' ))
                     . subList( array( T_('Start next round') ))
              );
   }// round-robin | league

   return '';
}//make_links_ttype_specific

function subList( $arr, $class='SubList' )
{
   if ( count($arr) == 0 )
      return '';
   $class_str = ($class != '') ? " class=\"$class\"" : '';
   return "<ul{$class_str}><li>" . implode("</li>\n<li>", $arr) . "</li></ul>\n";
}//subList

function make_admin_tgame( $tid )
{
   global $base_path;
   $label_textbox = span('TAdmin', T_('Admin tournament game')) . ', ' . T_('Enter Game ID');
   $label_submit = T_('Edit#2');

   return <<<___FORMEND___
      <FORM action="{$base_path}tournaments/game_admin.php" method="GET">
      <INPUT type="hidden" name="tid" value="$tid">
      $label_textbox: <INPUT type="text" name="gid" value="" size="8" maxlength="8">
      <INPUT type="submit" name="atg" value="$label_submit">
___FORMEND___;
}//make_admin_tgame
?>
