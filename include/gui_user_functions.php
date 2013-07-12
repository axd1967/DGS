<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Users";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';



/*!
 * \brief Parses and checks list of users specified as numeric user-id (456), numeric handle (=123) or textual handle (abc).
 * \param $user_list string with users
 * \param $author_uid user-id to check for contacts if rejecting message from author
 * \return [ handle-arr, uid-arr, User-row-arr, handle-reject-map( Handle => 1 ), errors ]
 */
function check_user_list( $user_list, $author_uid )
{
   $errors = array();
   $arr_uid = array();
   $arr_handle = array();
   $arr_miss = array();

   foreach ( preg_split("/[\\s,;]+/", $user_list) as $u )
   {
      if ( strlen($u) == 0 )
         continue;

      if ( $u[0] == '=' ) // =1234 (support for numeric handle), allowed for non-numeric-handle too
      {
         $is_handle = true;
         $u = substr($u, 1);
      }
      else
         $is_handle = false;

      if ( !is_numeric($u) && illegal_chars($u) )
         $errors[] = sprintf( T_('Illegal characters used in user [%s]#userlist'), $u );
      else
      {
         if ( $is_handle || !is_numeric($u) )
            $arr_handle[] = $u;
         else
            $arr_uid[] = $u;
         $arr_miss[strtolower($u)] = 1;
      }
   }

   $handles = array();
   $uids = array();
   $urefs = array();
   $rejectmsg = array();
   $deny_survey = array();

   $qsql = new QuerySQL(
      SQLP_FIELDS, 'P.ID', 'P.Handle', 'P.Name', 'P.AdminOptions',
      SQLP_FROM,   'Players AS P' );
   if ( $author_uid > 0 )
   {
      $qsql->add_part( SQLP_FIELDS, 'IFNULL(C.uid,0) AS C_RejectMsg');
      $qsql->add_part( SQLP_FROM,
         "LEFT JOIN Contacts AS C ON C.uid=P.ID AND C.cid=$author_uid AND (C.SystemFlags & ".CSYSFLAG_REJECT_MESSAGE.")" );
   }
   $user_sql = $qsql->get_select();

   if ( count($arr_uid) > 0 )
   {
      $result = db_query( "check_user_list.uids($author_uid)",
         "$user_sql WHERE P.ID IN (".implode(',', $arr_uid).") LIMIT " . count($arr_uid) );
      while ( $row = mysql_fetch_array( $result ) )
      {
         $uid = $row['ID'];
         $uids[] = $uid;
         $handles[$uid] = $view_handle = ( (is_numeric($row['Handle'])) ? '=' : '' ) . $row['Handle'];
         $urefs[$uid] = $row;
         if ( $author_uid > 0 && @$row['C_RejectMsg'] )
            $rejectmsg[$view_handle] = 1;
         if ( $author_uid == 0 && (@$row['AdminOptions'] & ADMOPT_DENY_SURVEY_VOTE) )
            $deny_survey[$view_handle] = 1;
         unset($arr_miss[$uid]);
      }
      mysql_free_result($result);
   }

   if ( count($arr_handle) > 0 )
   {
      $result = db_query( "check_user_list.handles($author_uid)",
         "$user_sql WHERE P.Handle IN ('".implode("','", $arr_handle)."') LIMIT " . count($arr_handle) );
      while ( $row = mysql_fetch_array( $result ) )
      {
         $uid = $row['ID'];
         $handle = $row['Handle'];
         $uids[] = $uid;
         $handles[$uid] = $view_handle = ( (is_numeric($handle)) ? '=' : '' ) . $handle;
         $urefs[$uid] = $row;
         if ( $author_uid > 0 && @$row['C_RejectMsg'] )
            $rejectmsg[$view_handle] = 1;
         if ( $author_uid == 0 && (@$row['AdminOptions'] & ADMOPT_DENY_SURVEY_VOTE) )
            $deny_survey[$view_handle] = 1;
         unset($arr_miss[strtolower($handle)]);
      }
      mysql_free_result($result);
   }

   $uids = array_unique($uids);
   ksort( $handles, SORT_NUMERIC );
   ksort( $uids, SORT_NUMERIC );
   ksort( $urefs, SORT_NUMERIC );

   if ( count($arr_miss) > 0 )
      $errors[] = sprintf( T_('Unknown users found [%s]#userlist'), implode(', ', array_keys($arr_miss) ));
   if ( $author_uid > 0 && count($uids) == 1 )
      $errors[] = T_('Userlist must contain at least two recipients, otherwise send a private message instead.#userlist');
   if ( count($deny_survey) > 0 )
      $errors[] = sprintf( T_('Users [%s] are denied to vote on surveys by admin.#userlist'),
         implode(' ', array_keys($deny_survey)) );

   $guests = array();
   for ( $g_uid=1; $g_uid <= GUESTS_ID_MAX; $g_uid++)
   {
      if ( isset($handles[$g_uid]) )
         $guests[] = $handles[$g_uid];
   }
   if ( count($guests) > 0 )
      $errors[] = sprintf( T_('Guest users [%s] are not allowed in user-list.#userlist'), implode(' ', $guests) );

   return array( array_unique(array_values($handles)), $uids, $urefs, array_keys($rejectmsg), $errors );
}//check_user_list

?>
