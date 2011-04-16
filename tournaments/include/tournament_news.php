<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Tournament";

require_once 'include/utilities.php';
require_once 'include/db_classes.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament.php';

 /*!
  * \file tournament_news.php
  *
  * \brief Functions for handling tournament news: tables TournamentNews
  */


 /*!
  * \class TournamentNews
  *
  * \brief Class to manage TournamentNews-table
  */

// lazy-init in TournamentNews::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_NEWS; //PHP5
$ARR_GLOBALS_TOURNAMENT_NEWS = array();

global $ENTITY_TOURNAMENT_NEWS; //PHP5
$ENTITY_TOURNAMENT_NEWS = new Entity( 'TournamentNews',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_CHBY,
      FTYPE_INT,  'ID', 'tid', 'uid', 'Flags',
      FTYPE_TEXT, 'Subject', 'Text',
      FTYPE_DATE, 'Published', 'Lastchanged',
      FTYPE_ENUM, 'Status'
   );

class TournamentNews
{
   var $ID;
   var $tid;
   var $uid;
   var $Status; // null | TNEWS_STATUS_...
   var $Flags; // TNEWS_FLAG_...
   var $Published;
   var $Subject;
   var $Text;
   var $Lastchanged;
   var $ChangedBy;

   // non-DB fields

   var $User; // User-object

   /*! \brief Constructs TournamentNews-object with specified arguments. */
   function TournamentNews( $id=0, $tid=0, $uid=0, $user=null, $status=TNEWS_STATUS_NEW, $flags=0,
         $published=0, $subject='', $text='', $lastchanged=0, $changed_by='' )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->uid = (int)$uid;
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->Published = (int)$published;
      $this->Subject = $subject;
      $this->Text = $text;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      // non-DB fields
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_TNEWS_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentNews.setStatus($status)");
      $this->Status = $status;
   }

   function to_string()
   {
      return " ID=[{$this->ID}]"
            . ", tid=[{$this->tid}]"
            . ", uid=[{$this->uid}]"
            . sprintf( ", User=[%s]", $this->User->to_string() )
            . ", Status=[{$this->Status}]"
            . sprintf( ",Flags=[0x%x]", $this->Flags)
            . ", Published=[{$this->Published}]"
            . ", Subject=[{$this->Subject}]"
            . ", Text=[{$this->Text}]"
            . ", Lastchanged=[{$this->Lastchanged}]"
            . ", ChangedBy=[{$this->ChangedBy}]"
         ;
   }

   /*! \brief Inserts or updates tournament-news in database. */
   function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "TournamentNews::insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentNews::update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentNews::delete(%s)" );
   }

   function checkData()
   {
      if( is_null($this->Status) )
         error('invalid_args', "TournamentNews.checkData.miss_status({$this->ID},{$this->tid})");
   }

   function fillEntityData()
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_NEWS']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Published', $this->Published );
      $data->set_value( 'Subject', $this->Subject );
      $data->set_value( 'Text', $this->Text );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      return $data;
   }

   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentNews-object. */
   function build_query_sql()
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_NEWS']->newQuerySQL('TN');
      $qsql->add_part( SQLP_FIELDS,
         'TN.uid AS TNP_ID',
         'TNP.Name AS TNP_Name',
         'TNP.Handle AS TNP_Handle' );
      $qsql->add_part( SQLP_FROM,
         'INNER JOIN Players AS TNP ON TNP.ID=TN.uid' );
      return $qsql;
   }

   /*! \brief Returns TournamentNews-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tn = new TournamentNews(
            // from TournamentNews
            @$row['ID'],
            @$row['tid'],
            @$row['uid'],
            User::new_from_row( $row, 'TNP_' ), // from Players TNP
            @$row['Status'],
            @$row['Flags'],
            @$row['X_Published'],
            @$row['Subject'],
            @$row['Text'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy']
         );
      return $tn;
   }

   /*! \brief Loads and returns TournamentNews-object for given tournament-news-ID; NULL if nothing found. */
   function load_tournament_news_entry( $tnews_id, $tid=0 )
   {
      $result = NULL;
      if( $tnews_id > 0 )
      {
         $qsql = TournamentNews::build_query_sql();
         $qsql->add_part( SQLP_WHERE, "TN.ID='$tnews_id'" );
         if( $tid > 0 )
            $qsql->add_part( SQLP_WHERE, "TN.tid='$tid'" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentNews.load_tournament_news($tnews_id,$tid)", $qsql->get_select() );
         if( $row )
            $result = TournamentNews::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentNews-objects of given tournament. */
   function load_tournament_news( $iterator, $tid )
   {
      $qsql = TournamentNews::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "TN.tid='$tid'" );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentNews.load_tournament_participants($tid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentNews::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Deletes all tournament-news on DELETE-status older than given number days (can be negative). */
   function process_tournament_news_deleted( $days_age )
   {
      global $NOW;
      if( !is_numeric($days_age) )
         error('invalid_args', "TournamentNews::process_tournament_news_deleted($days_age)");

      db_query( "TournamentNews.process_tournament_news_deleted($days_age)",
         "DELETE FROM TournamentNews WHERE Status='".TNEWS_STATUS_DELETE."' AND " .
            "Lastchanged < FROM_UNIXTIME($NOW) - INTERVAL $days_age DAY" );
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_TOURNAMENT_NEWS;

      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT_NEWS['STATUS']) )
      {
         $arr = array();
         $arr[TNEWS_STATUS_NEW]     = T_('New#TN_status');
         $arr[TNEWS_STATUS_SHOW]    = T_('Show#TN_status');
         $arr[TNEWS_STATUS_ARCHIVE] = T_('Archive#TN_status');
         $arr[TNEWS_STATUS_DELETE]  = T_('Delete#TN_status');
         $ARR_GLOBALS_TOURNAMENT_NEWS['STATUS'] = $arr;
      }

      $key = 'STATUS';
      if( is_null($status) )
         return $ARR_GLOBALS_TOURNAMENT_NEWS[$key];

      if( !isset($ARR_GLOBALS_TOURNAMENT_NEWS[$key][$status]) )
         error('invalid_args', "TournamentNews.getStatusText($status,$key)");
      return $ARR_GLOBALS_TOURNAMENT_NEWS[$key][$status];
   }

   /*! \brief Returns flags-text for given int-bitmask or all flags-texts (if arg=null). */
   function getFlagsText( $flags=null )
   {
      global $ARR_GLOBALS_TOURNAMENT_NEWS;

      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT_NEWS['FLAGS']) )
      {
         $arr = array();
         $arr[TNEWS_FLAG_HIDDEN]    = T_('Hidden#TN_flag');
         $arr[TNEWS_FLAG_PRIVATE]   = T_('Private#TN_flag');
         $ARR_GLOBALS_TOURNAMENT_NEWS['FLAGS'] = $arr;
      }
      else
         $arr = $ARR_GLOBALS_TOURNAMENT_NEWS['FLAGS'];
      if( is_null($flags) )
         return $arr;

      $out = array();
      foreach( $arr as $flagmask => $flagtext )
         if( $flags & $flagmask ) $out[] = $flagtext;
      return implode(', ', $out);
   }

   /*! \brief Prints formatted tournament-news with CSS-style with title, publish-date, author and text. */
   function build_tournament_news( $tnews )
   {
      $title = make_html_safe($tnews->Subject, true);
      $text = make_html_safe($tnews->Text, true);
      $publish_text = sprintf( T_('[%s] by %s#tnews_publish'),
         date(DATE_FMT2, $tnews->Published), $tnews->User->user_reference() );
      return
         "<div class=\"TournamentNews\">\n" .
            "<div class=\"Title\">$title</div>" .
            "<div class=\"Published\">$publish_text</div>" .
            "<div class=\"Text\">$text</div>" .
         "</div>\n";
   }

} // end of 'TournamentNews'

?>
