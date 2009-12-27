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

chdir( '../../' );
require_once( "include/std_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'fix_message_thread-1_0_15');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_message_thread', false );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   if( $do_it )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
      }
      _echo(
         "<p>*** Fixes message threads ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>" );
   }
   else
   {
      function dbg_query($s) { _echo( "<BR>$s; "); }
      $tmp = array_merge($page_args,array('do_it' => 1));
      _echo(
         "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>" );
   }


   // NOTE: This may run into memory problems, because it needs to load all messages
   //       to update at once. So it's good to have some preparations done before.
   //       see scripts/updates/database_changes_1_0_14_to_1_0_15.mysql

   _echo( "<hr>Find messages without thread ..." );

   $query = "SELECT M.ID AS mid, M.ReplyTo, M2.Thread, M2.Level "
      . "FROM Messages AS M INNER JOIN Messages AS M2 ON M.ReplyTo=M2.ID "
      . "WHERE M.ReplyTo>0 and M.Thread=0";
   $result = mysql_query( $query ) or die(mysql_error());

   // read all
   $upd_msgs = array();   // msg-id => [ thread to upd, level to upd ]
   $threads = array();    // msg-id => thread
   $levels = array();     // msg-id => level
   $todo_msgs = array();
   while( ($row = mysql_fetch_assoc( $result )) )
   {
      $mid    = $row['mid'];
      $reply  = $row['ReplyTo'];
      $thread = $row['Thread'];
      $level  = $row['Level'];

      if( $thread > 0 )
      {
         $threads[$reply] = $thread;
         $threads[$mid] = $thread;
         $levels[$reply] = $level + 1;
         $levels[$mid] = $level + 1;
         $upd_msgs[$mid] = array( $thread, $level );
      }
      else
         $todo_msgs[$mid] = $reply;
   }
   mysql_free_result($result);

   $msg_cnt = count($todo_msgs) + count($upd_msgs);
   $curr_cnt = 0;
   _echo( "<br>Found $msg_cnt messages to process and set thread ...\n" );

   update_all_messages( $upd_msgs );
   unset($upd_msgs); // same some memory

   // process all
   $last_count = -1;
   while( count($todo_msgs) != $last_count )
   {
      $last_count = count($todo_msgs);
      _echo( "<br>... $last_count rows left to resolve ...\n" );

      $arr = array(); // one run should suffice normally
      foreach( $todo_msgs as $mid => $reply )
      {
         if( isset($threads[$reply]) )
         {
            $threads[$mid] = $threads[$reply];
            $levels[$mid]  = $levels[$reply] + 1;
            update_message( $mid, $threads[$mid], $levels[$mid] );
         }
         else
            $arr[$mid] = $reply;
      }
      $todo_msgs = $arr;
   }

   if( $do_it )
      _echo('Message thread fix finished.');

   end_html();
}

function _echo($msg)
{
   echo $msg;
   ob_flush();
   flush();
}

function update_all_messages( $arr_update )
{
   global $msg_cnt, $curr_cnt;
   _echo( sprintf( "<br><br>%s remaining updates on Messages ...\n", $msg_cnt - $curr_cnt ));

   foreach( $arr_update as $mid => $arr )
   {
      list( $thread, $level ) = $arr;
      update_message( $mid, $thread, $level );
   }
}

function update_message( $mid, $thread, $level )
{
   global $msg_cnt, $curr_cnt;
   if( ($curr_cnt++ % 100) == 0 )
      _echo( "<br><br>... $curr_cnt of $msg_cnt updated ...\n" );
   $update_query = "UPDATE Messages SET Thread='$thread', Level='$level' WHERE ID='$mid' LIMIT 1";
   dbg_query($update_query);
}

?>
