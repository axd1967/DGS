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

 /* The code in this file is written by Ragnar Ouchterlony */

require_once( "include/std_functions.php" );
require_once( "include/tournament.php" );

function display_row_of_information( $description, $information )
{
  echo "  <tr><td align=\"left\"><b>&nbsp;$description&nbsp;</b></td>" .
    "<td align=\"left\">&nbsp;$information&nbsp;</td></tr>\n";
}

function make_max_min_participants_string( $min, $max )
{
   if( is_null( $max ) )
   {
      return sprintf( T_("At least %s participants"), $min );
   }

   return sprintf( T_("Between %1\$s and %2\$s participants"), $min, $max );
}

function make_applicationperiod_string( $app_p, $soap )
{
   global $NOW, $date_fmt2;

   if( is_null( $app_p ) or is_null( $soap ) )
   {
      return T_("Applicationperiod has not yet been decided");
   }

   $eoap = strtotime( "+$app_p days", $soap );
   $start_string = date($date_fmt2, $soap);
   $end_string = date($date_fmt2, $eoap);

   $resultstring = "";
   if( $NOW < $soap )
   {
      $resultstring =
         sprintf( T_("The application period will start %1\$s, and end %2\$s"),
                  $start_string, $end_string );
   }
   elseif( $NOW >= $soap and $NOW < $eoap )
   {
      $resultstring =
         sprintf( T_("The application period started %1\$s, and will end %2\$s"),
                  $start_string, $end_string );
   }
   else
   {
      $resultstring =
         sprintf( T_("The application period was started %1\$s, and ended %2\$s"),
                  $start_string, $end_string );
   }

   return $resultstring;
}

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( !isset( $_GET['tid'] ) )
   {
      error("strange_tournament_id");
   }

   $t = new Tournament( $_GET['tid'] );

   if( isset( $_GET['update'] ) and
       $_GET['update'] == 't' and
       in_array( $player_row['ID'], $t->ListOfOrganizers ) )
   {
      $t->Name = trim( $_POST['name'] );
      $t->Description = trim( $_POST['description'] );

      $query = "UPDATE Tournament SET " .
         "Name='" . $t->Name . "', " .
         "Description='" . $t->Description . "', ";


      if( isset( $_POST['minpart'] ) and is_numeric( $_POST['minpart'] ) )
      {
         $t->MinParticipants = $_POST['minpart'];
         $query .= "MinParticipants=" . $t->MinParticipants . ", ";
      }

      if( isset( $_POST['maxpart'] ) and is_numeric( $_POST['maxpart'] ) )
      {
         $t->MaxParticipants = $_POST['maxpart'];
         $query .= "MaxParticipants=" . $t->MaxParticipants . ", ";
      }
      else
      {
         $t->MaxParticipants = null;
         $query .= "MaxParticipants=NULL, ";
      }

      $current_time = getdate( $NOW );
      if( isset( $_POST['ap_start_day'] ) and
          is_numeric( $_POST['ap_start_day'] ) and
          $_POST['ap_start_day'] >= 1 and
          $_POST['ap_start_day'] <= 31 and

          isset( $_POST['ap_start_month'] ) and
          is_numeric( $_POST['ap_start_month'] ) and
          $_POST['ap_start_month'] >= 1 and
          $_POST['ap_start_month'] <= 12 and

          isset( $_POST['ap_start_year'] ) and
          is_numeric( $_POST['ap_start_year'] ) and
          $_POST['ap_start_year'] >= $current_time['year'] and
          $_POST['ap_start_year'] <= $current_time['year']+2 )
      {
         $t->StartOfApplicationPeriod = 
            mktime( 0, 0, 0,
                    $_POST['ap_start_month'],
                    $_POST['ap_start_day'],
                    $_POST['ap_start_year'] );
         $query .= "StartOfApplicationPeriod=FROM_UNIXTIME(" .
            $t->StartOfApplicationPeriod . "), ";
      }

      if( isset( $_POST['ap_length'] ) and is_numeric( $_POST['ap_length'] ) )
      {
         $t->ApplicationPeriod = $_POST['ap_length'];
         $query .= "ApplicationPeriod=" . $t->ApplicationPeriod . ", ";
      }
      else
      {
         $t->ApplicationPeriod = null;
         $query .= "ApplicationPeriod=NULL, ";
      }

      $t->StrictEndOfApplicationPeriod =
         ( $_POST['strictend'] == 'Y' ? true : false );
      $t->ReceiveApplicationsAfterStart =
         ( $_POST['receive_after_start'] == 'Y' ? true : false );
      $t->Rated = ( $_POST['rated'] == 'Y' ? true : false );
      $t->WeekendClock =( $_POST['weekend'] == 'Y' ? true : false );

      $query .=
         "StrictEndOfApplicationPeriod='" .
         ($t->StrictEndOfApplicationPeriod ? 'Y' : 'N' ) . "', " .
         "ReceiveApplicationsAfterStart='" .
         ($t->ReceiveApplicationsAfterStart ? 'Y' : 'N' ) . "', " .
         "Rated='" . ($t->Rated ? 'Y' : 'N' ) . "', " .
         "WeekendClock='" . ($t->WeekendClock ? 'Y' : 'N' ) . "'";

      mysql_query( $query )
         or error("couldnt_update_tournament");
   }

   start_page(T_("Show tournament"), true, $logged_in, $player_row );

   if( isset( $_GET['modify'] ) and
       $_GET['modify'] == 't' and
       in_array( $player_row['ID'], $t->ListOfOrganizers ) )
   {
      echo "<center>\n";
      $modify_form = new Form( 'modifyform',
                               "show_tournament.php?tid=" . $t->ID . "&update=t",
                               FORM_POST );
      $modify_form->add_row( array( 'HEADER', T_('Tournament') ) );
      $modify_form->add_row( array( 'DESCRIPTION', T_('Tournament ID'),
                                    'TEXT', $t->ID ) );
      $modify_form->add_row( array( 'DESCRIPTION', T_('Tournament name'),
                                    'TEXTINPUT', 'name', 50, 80, $t->Name ) );
      $modify_form->add_row( array( 'DESCRIPTION', T_('Tournamnet description'),
                                    'TEXTAREA', 'description', 50, 8, $t->Description ) );
      $modify_form->add_row( array( 'DESCRIPTION', T_('Allowed participants'),
                                    'TEXT', T_('Between'),
                                    'TEXTINPUT', 'minpart', 6, 10, $t->MinParticipants,
                                    'TEXT', "&nbsp;" . T_('and') . "&nbsp;",
                                    'TEXTINPUT', 'maxpart', 6, 10, $t->MaxParticipants,
                                    'TEXT', "&nbsp;" . T_('particpants') ) );

      $current_month = getdate( $NOW );
      if( is_null( $t->StartOfApplicationPeriod ) )
      {
         $default_day = array( 'mday' => 0, 'mon' => 0, 'year' => 0 );
      }
      else
      {
         $default_day = getdate( $t->StartOfApplicationPeriod );
      }

      $day_array = array( 0 => '' );
      for( $bs = 1; $bs <= 31; $bs++ )
      {
         $day_array[$bs]=$bs;
      }

      $month_array = array( 0 => '',
                            1 => T_('January'), T_('February'), T_('March'),
                            T_('April'),        T_('May'),      T_('June'),
                            T_('July'),         T_('August'),   T_('September'),
                            T_('October'),  T_('November'), T_('December') );
      $year_array = array( 0 => '' );
      for( $bs = $current_month['year']; $bs <= $current_month['year']+2; $bs++ )
      {
         $year_array[$bs]=$bs;
      }

      $modify_form->add_row( array( 'DESCRIPTION', T_('Applicationperiod'),
                                    'TEXT', T_('Starting'),
                                    'SELECTBOX', 'ap_start_day', 1,
                                    $day_array, $default_day['mday'], false,
                                    'SELECTBOX', 'ap_start_month', 1,
                                    $month_array, $default_day['mon'], false,
                                    'SELECTBOX', 'ap_start_year', 1,
                                    $year_array, $default_day['year'], false,
                                    'TEXT', "&nbsp;" . T_('and continuing for') . "&nbsp;",
                                    'TEXTINPUT', 'ap_length', 6, 10, $t->Applicationperiod,
                                    'TEXT', "&nbsp;" . T_('days') ) );

      $modify_form->add_row( array( 'DESCRIPTION', T_('Tournament state'),
                                    'TEXT', $TourState_Strings[ $t->State ] ) );
      $modify_form->add_row( array( 'DESCRIPTION', T_('Strict end of application period?'),
                                    'CHECKBOX', 'strictend', 'Y', "",
                                    $t->StrictEndOfApplicationPeriod ) );
      $modify_form->add_row( array( 'DESCRIPTION',
                                    T_('Will tournament receive apllications after start?'),
                                    'CHECKBOX', 'receive_after_start', 'Y', "",
                                    $t->ReceiveApplicationsAfterStart ) );
      $modify_form->add_row( array( 'DESCRIPTION', T_('Rated tournament games?'),
                                    'CHECKBOX', 'rated', 'Y', "", $t->Rated ) );
      $modify_form->add_row( array( 'DESCRIPTION', T_('Weekend clock in tournament games?'),
                                    'CHECKBOX', 'weekend', 'Y', "", $t->WeekendClock ) );
      $modify_form->add_row( array( 'SUBMITBUTTON', 'action', T_('Update') ) );
      $modify_form->echo_string();

      echo "<a href=\"show_tournament.php?tid=" . $t->ID . "\">View tournament</a>";
      echo "</center>\n";
   }
   else
   {
      echo "<table align=\"center\" border=\"3\" cellspacing=\"0\" cellpadding=\"3\">\n";
      display_row_of_information( T_('ID'), $t->ID );
      display_row_of_information( T_('Name'), $t->Name );
      display_row_of_information( T_('Description'), str_replace( "\n", "\n<br>&nbsp;",
                                                                  $t->Description ) );
      display_row_of_information( T_('Organizers'), $t->get_organizers_html() );
      display_row_of_information( T_('Max and min participants'),
                                  make_max_min_participants_string( $t->MinParticipants,
                                                                 $t->MaxParticipants ) );
      display_row_of_information( T_('Tournament state'), $TourState_Strings[ $t->State ] );
      display_row_of_information( T_('Applicationperiod'),
                                  make_applicationperiod_string( $t->ApplicationPeriod,
                                                              $t->StartOfApplicationPeriod ) );
      display_row_of_information( T_('Strict end of application period?'),
                                  ($t->StrictEndOfApplicationPeriod ? T_("Yes") : T_("No")) );
      display_row_of_information( T_('Will tournament receive apllications after start?'),
                                  ($t->ReceiveApplicationsAfterStart ? T_("Yes") : T_("No")) );
      display_row_of_information( T_('Rated tournament games?'),
                                  ($t->Rated ? T_("Yes") : T_("No")) );
      display_row_of_information( T_('Weekend clock in tournament games?'),
                                  ($t->WeekendClock ? T_("Yes") : T_("No")) );

      echo "</table>\n";

      if( in_array( $player_row['ID'], $t->ListOfOrganizers ) )
      {
         echo "<br><br><center><a href=\"show_tournament.php?tid=" . $t->ID . "&modify=t\">Modify tournament</a></center>\n";
      }
   }

   end_page(false);
}
?>
