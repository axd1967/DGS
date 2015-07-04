<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/mail_functions.php';
require_once 'include/board.php';

$TheErrors->set_mode(ERROR_MODE_COLLECT);

if ( !$is_down )
{
   $half_diff = SECS_PER_HOUR / 2;
   if ( $chained )
      $chained = $half_diff;
   else
      connect2mysql();
   $max_run_time = $NOW + $half_diff - 2 * SECS_PER_MIN; // "buffer" of 2min for long-lasting "last" query before stopping script
   $half_diff -= 5 * SECS_PER_MIN;
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'halfhourly_cron.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff, Ticks " .
      "FROM Clock " .
      "WHERE ID=".CLOCK_CRON_HALFHOUR." LIMIT 1" );
   if ( !$row )
      $TheErrors->dump_exit('halfhourly_cron.find_clock');
   if ( $row['timediff'] < $half_diff )
      $TheErrors->dump_exit('halfhourly_cron.timediff');

   // check for concurrent-runs as script may run longer than 30mins
   $clock_ticks = (int)@$row['Ticks'];
   if ( $clock_ticks > 0 )
   {
      db_query( "halfhourly_cron.inc_ticks($clock_ticks)",
            "UPDATE Clock SET Ticks=Ticks+1 WHERE ID=".CLOCK_CRON_HALFHOUR." AND Ticks=$clock_ticks LIMIT 1" )
         or $TheErrors->dump_exit("halfhourly_cron.inc_ticks2($clock_ticks)");

      if ( EMAIL_ADMINS )
      {
         send_email("halfhourly_cron.concurrent_run($clock_ticks)", EMAIL_ADMINS, 0,
            sprintf("Detected concurrent runs (Ticks=%s) of the halfhourly-cron-script running on [%s].\n" .
                    "Please check and correct if neccessary!", $clock_ticks, HOSTBASE),
            sprintf('[%s] CHECK halfhourly_cron.php on [%s] (%s)', FRIENDLY_SHORT_NAME, HOSTBASE, $clock_ticks ) );
      }
      $TheErrors->dump_exit("halfhourly_cron.concurrent_run($clock_ticks)");
   }//concurrent-run-check

   db_query( 'halfhourly_cron.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=".CLOCK_CRON_HALFHOUR." AND Ticks=0 LIMIT 1" )
      or $TheErrors->dump_exit('halfhourly_cron.lock.1');
   if ( mysql_affected_rows() != 1 )
      $TheErrors->dump_exit('halfhourly_cron.lock.2');



   // ---------- BEGIN ------------------------------

   // ---------- Update activities

   //close to 0.9964 for a four days halving time (in minutes)
   $factor = exp( -M_LN2 * 30 / $ActivityHalvingTime );

   //the WHERE is just added here to avoid to update the *whole* table each time
   //the FLOOR and integer type also help the re-construction of the index of the column
   db_query( 'halfhourly_cron.activity',
      "UPDATE Players SET Activity=FLOOR($factor*Activity) WHERE Activity>0" );



   // ---------- Check end of vacations and reset associated game clocks

   $max_vacations = 365.24/12; //1 month [days]
   $this_ticks_per_day = 2*24;

   $result = db_query( 'halfhourly_cron.onvacation',
      "SELECT ID, ClockUsed FROM Players " .
      "WHERE OnVacation>0 AND OnVacation<=1/($this_ticks_per_day)" );

   ta_begin();
   {
      while ( $prow = mysql_fetch_assoc( $result ) )
      {
         $uid = $prow['ID'];
         $ClockUsed = $prow['ClockUsed'];
         $WeekendClockUsed = $ClockUsed + WEEKEND_CLOCK_OFFSET;

         // NOTE: LastTicks handle -(time spent) at the moment of the start of vacations
         //       inserts this spent time into the (possibly new) ClockUsed by the player.
         // NOTE: use weekendclock on Games.WeekendClock=N, otherwise use normal clock
         db_query( "edit_vacation.update_games.weekend_clock_running($uid)",
            "UPDATE Games"
            ." INNER JOIN Clock ON Clock.ID=$ClockUsed"
            ." SET Games.ClockUsed=$ClockUsed"
               .", Games.LastTicks=Games.LastTicks+Clock.Ticks"
            ." WHERE Games.Status" . IS_STARTED_GAME
            ." AND Games.ToMove_ID=$uid"
            ." AND Games.ClockUsed<0" // VACATION_CLOCK
            ." AND Games.WeekendClock='Y'" // weekend-clock running
            );
         db_query( "edit_vacation.update_games.weekend_clock_stopped($uid)",
            "UPDATE Games"
            ." INNER JOIN Clock ON Clock.ID=$WeekendClockUsed"
            ." SET Games.ClockUsed=$WeekendClockUsed"
               .", Games.LastTicks=Games.LastTicks+Clock.Ticks"
            ." WHERE Games.Status" . IS_STARTED_GAME
            ." AND Games.ToMove_ID=$uid"
            ." AND Games.ClockUsed<0" // VACATION_CLOCK
            ." AND Games.WeekendClock='N'" // weekend-clock stopped
            );
      }
      mysql_free_result($result);
   }
   ta_end();



   // ---------- Change vacation days

   db_query( 'halfhourly_cron.vacation_days.increase', // -1 day after 1 day
      "UPDATE Players SET "
      . "OnVacation=GREATEST(0, OnVacation - 1/($this_ticks_per_day))"
      . " WHERE OnVacation>0" );

   db_query( 'halfhourly_cron.on_vacation.decrease', // +1 day after 12 days
      "UPDATE Players SET "
      . "VacationDays=LEAST($max_vacations, VacationDays + 1/(12*$this_ticks_per_day))"
      . " WHERE VacationDays<$max_vacations" );



   // ---------- Send notifications

   // NOTE: do longest-lasting updates as last task

   // Email notification if the SendEmail 'ON' flag is set
   // more infos if other SendEmail flags are set


   // Setting Notify to 'NOW' for next bunch of notifications (to avoid race-conditions while directly processing 'NEXT')
   db_query( 'halfhourly_cron.update_players_notify_now',
         "UPDATE Players SET Notify='NOW' " .
         "WHERE Notify='NEXT' AND FIND_IN_SET('ON',SendEmail)");

   // pre-load all users to notify (to free db-result as soon as possible as main-loop can take quite a while)
   $result = db_query( 'halfhourly_cron.find_notifications',
         "SELECT ID AS uid, Handle, Email, SendEmail, NotifyFlags, UserFlags, " .
            "UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess, UNIX_TIMESTAMP(LastNotified) AS X_LastNotified " .
         "FROM Players " .
         "WHERE Notify='NOW' AND FIND_IN_SET('ON',SendEmail) " .
         "ORDER BY Lastaccess ASC"); // oldest-access users first (as newer-access-users can see new games/messages anyway)
   $nfyuser_iterator = new ListIterator( "halfhourly_cron.load_nfyuser.Players" );
   while ( $row = mysql_fetch_array( $result ) )
      $nfyuser_iterator->addItem( null, $row );
   mysql_free_result($result);

   // loop over users to notify
   $cnt_notifies = 0;
   $begin_time_notifies = time();
   while ( list(, $arr_item) = $nfyuser_iterator->getListIterator() )
   {
      if ( time() > $max_run_time ) break; // stop script if running too long to avoid chance of concurrent runs
      $row = $arr_item[1];
      extract($row);
      $collect_time = ( $NotifyFlags & NOTIFYFLAG_LAST_NOTIFIED )
         ? "FROM_UNIXTIME($X_LastNotified)"
         : "FROM_UNIXTIME($X_Lastaccess)";
      $user_new_notified = time() + $timeadjust;

      // check for valid email
      $Email = trim($Email);
      if ( !$Email || verify_invalid_email("halfhourly_cron($uid)", $Email, /*err-die-collect-errors*/true) )
      {
         if ( !($UserFlags & USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL) )
         {
            send_message( "halfhourly_cron.notify.bad_email($uid)",
               sprintf( T_("You have enabled email notifications, which requires a valid email, " .
                           "but there is no or an invalid email-address [%s] in your user profile.\n\n" .
                           "Therefore all mail notifications are skipped till you have corrected your email-address " .
                           "in your <home %s>user profile</home>."),
                        $Email, 'edit_profile.php' ),
               T_('Invalid email detected'),
               $uid, '', /*notify*/false );
         }

         db_query( "halfhourly_cron.email_check.nfy_done($uid)[$Email]",
            "UPDATE Players " .
            "SET UserFlags=UserFlags | ".USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL.", Notify='DONE' " .
            "WHERE ID=$uid AND Notify='NOW' LIMIT 1" );
         continue;
      }//no-or-invalid-email

      $msg = sprintf( "A message or game move is waiting for you [ %s ] at:\n ", $Handle )
                . mail_link('',"status.php")."\n"
           . "(No more notifications will be sent until your reconnection)\n";
      $begin_time = time();

      // Find games

      if ( strpos($SendEmail, 'MOVE') !== false ) // game: move + optional board
      {
         $query = "SELECT Games.*, " .
            "black.Name AS Blackname, " .
            "black.Handle AS Blackhandle, " .
            "white.Name AS Whitename, " .
            "white.Handle AS Whitehandle " .
            "FROM Games " .
               "INNER JOIN Players AS black ON black.ID=Games.Black_ID " .
               "INNER JOIN Players AS white ON white.ID=Games.White_ID " .
            "WHERE ToMove_ID=$uid AND Lastchanged >= $collect_time";
         $gres = db_query( "halfhourly_cron.find_games($uid)", $query );

         // pre-load all games of user to notify (to free db-result as soon as possible)
         $games_iterator = new ListIterator( "halfhourly_cron.find_games($uid)" );
         if ( @mysql_num_rows($gres) > 0 )
         {
            while ( $row = mysql_fetch_array( $gres ) )
               $games_iterator->addItem( null, $row );
         }
         mysql_free_result($gres);

         // loop over games of user
         if ( $games_iterator->getItemCount() > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  Games:\n";

            while ( list(, $arr_item) = $games_iterator->getListIterator() )
            {
               $game_row = $arr_item[1];
               $gid = @$game_row['ID'];

               $msg .= str_pad('', 47, '-') . "\n";
               $msg .= "Game ID: ".mail_link($gid, 'game.php?gid='.$gid) . "\n";
               $msg .= "Black: ".mail_strip_html("{$game_row['Blackname']} ({$game_row['Blackhandle']})")."\n";
               $msg .= "White: ".mail_strip_html("{$game_row['Whitename']} ({$game_row['Whitehandle']})")."\n";

               $tmp = number2board_coords($game_row['Last_X'], $game_row['Last_Y'], $game_row['Size']);
               if ( empty($tmp) )
                  $tmp = 'lead to '.$game_row['Status'].' step';
               $msg .= 'Move '.$game_row['Moves'].": $tmp\n";

               if ( strpos($SendEmail, 'BOARD') !== false )
               {
                  $TheBoard = new Board();
                  if ( !$TheBoard->load_from_db($game_row, 0, BOARDOPT_MARK_DEAD|BOARDOPT_LOAD_LAST_MSG|BOARDOPT_STOP_ON_FIX) )
                     error('internal_error', "halfhourly_cron.game.load_from_db($gid,$uid)");

                  // remove hidden tags, strip away <c>-tags but keeping surrounded text
                  $movemsg = remove_hidden_game_tags( $TheBoard->movemsg );
                  $movemsg = trim( preg_replace( "'(<c(omment)?\\s*>(.*?)</c(omment)?\\s*>)'is", "\\3\n", $movemsg ) );
                  $movemsg = mail_strip_html($movemsg);
                  $msg .= $TheBoard->draw_ascii_board($movemsg) . "\n";
               }
            }
         }
         unset($TheBoard);
         unset($game_row);
      }//games of user
      $time_games = time();


      // Find new messages

      // only load if notify-flag set (to avoid too many slow-queries)
      if ( strpos($SendEmail, 'MESSAGE') !== false && ($NotifyFlags & NOTIFYFLAG_NEW_MSG) )
      {
         $folderstring = FOLDER_NEW;
         $query = "SELECT Messages.ID,Subject,Text, " .
            "UNIX_TIMESTAMP(Messages.Time) AS date, " .
            "Players.Name AS FromName, Players.Handle AS FromHandle " .
            "FROM Messages " .
               "INNER JOIN MessageCorrespondents AS me ON me.mid=Messages.ID " .
               "LEFT JOIN MessageCorrespondents AS other ON other.mid=me.mid AND other.Sender='Y' " .
               "LEFT JOIN Players ON Players.ID=other.uid " .
            "WHERE me.uid=$uid " .
              "AND me.Folder_nr IN ($folderstring) " .
              "AND me.Sender IN ('N','S') " . //exclude message to myself
              "AND Messages.Time > $collect_time " .
            "ORDER BY me.mid DESC"; // me.mid (=Messages.ID) has same order as Messages.Time (but does not use temp-table-sort)

         $res3 = db_query( "halfhourly_cron.find_new_messages($uid)", $query );
         if ( @mysql_num_rows($res3) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  New messages:\n";
            while ( $msg_row = mysql_fetch_array( $res3 ) )
            {
               $From = ( $msg_row['FromName'] && $msg_row['FromHandle'] )
                  ? mail_strip_html("{$msg_row['FromName']} ({$msg_row['FromHandle']})")
                  : 'Server message';

               $msg .= str_pad('', 47, '-') . "\n" .
                   "Message: ".mail_link('','message.php?mid='.$msg_row['ID']) . "\n" .
                   "Date: ".date(DATE_FMT, $msg_row['date']) . "\n" .
                   "From: $From\n" .
                   "Subject: ".mail_strip_html($msg_row['Subject']) . "\n\n" .
                   wordwrap(mail_strip_html($msg_row['Text']),70) . "\n";
            }
            unset($msg_row);
         }
         mysql_free_result($res3);
      }//messages of user
      $time_msgs = time();

      $msg .= str_pad('', 47, '-');


      // do not stop on mail-failure (but collect mail-errors)
      $nfy_done = send_email("halfhourly_cron($uid)", $Email, EMAILFMT_SKIP_WORDWRAP/*msg already wrapped*/, $msg );
      $time_mail = time();
      if ( $nfy_done )
      {
         // if loop fails, everyone would be notified again on next start -> so mark user as notified
         // Setting Notify to 'DONE' stop notifications until the player's next visit
         db_query( "halfhourly_cron.nfy_done($uid)",
            "UPDATE Players SET Notify='DONE', NotifyFlags=NotifyFlags & ~".NOTIFYFLAG_NEW_MSG .
               ", LastNotified=FROM_UNIXTIME($user_new_notified) " .
            "WHERE ID=$uid AND Notify='NOW' LIMIT 1" );
         $cnt_notifies++;
      }

      //error_log( sprintf("MONITOR[halfhourly_cron.mail_notifications](%s): GAME %s, MSG %s, MAIL %s", $uid, $time_games - $begin_time, $time_msgs - $time_games, $time_mail - $time_msgs ) );
   } //notifications found

   error_log( sprintf("MONITOR[halfhourly_cron.mail_notifications]: %s mails sent in %s sec",
      $cnt_notifies, time() - $begin_time_notifies ) );



   // ---------- END --------------------------------

   db_query( 'halfhourly_cron.reset_tick',
         "UPDATE Clock SET Ticks=0, Finished=FROM_UNIXTIME(".time().") WHERE ID=".CLOCK_CRON_HALFHOUR." LIMIT 1" );

   if ( !$chained )
      $TheErrors->dump_exit('halfhourly_cron');

}//$is_down
?>
