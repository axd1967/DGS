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
   private static $ARR_TNEWS_TEXTS = array(); // lazy-init in TournamentNews::get..Text()-funcs: [key][id] => text

   public $ID;
   public $tid;
   public $uid;
   public $Status; // null | TNEWS_STATUS_...
   public $Flags; // TNEWS_FLAG_...
   public $Published;
   public $Subject;
   public $Text;
   public $Lastchanged;
   public $ChangedBy;

   // non-DB fields

   public $User; // User-object

   /*! \brief Constructs TournamentNews-object with specified arguments. */
   public function __construct( $id=0, $tid=0, $uid=0, $user=null, $status=TNEWS_STATUS_NEW, $flags=0,
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
      $this->User = ($user instanceof User) ? $user : new User( $this->uid );
   }

   public function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_TNEWS_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentNews.setStatus($status)");
      $this->Status = $status;
   }

   public function to_string()
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
   public function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData(true);
      $result = $entityData->insert( "TournamentNews.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      self::delete_cache_tournament_news( 'TournamentNews.insert', $this->tid );
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $this->checkData();
      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentNews.update(%s)" );
      self::delete_cache_tournament_news( 'TournamentNews.update', $this->tid );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentNews.delete(%s)" );
      self::delete_cache_tournament_news( 'TournamentNews.delete', $this->tid );
      return $result;
   }

   // \internal
   private function checkData()
   {
      if( is_null($this->Status) )
         error('invalid_args', "TournamentNews.checkData.miss_status({$this->ID},{$this->tid})");
   }

   public function fillEntityData()
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

   /*! \brief Returns db-fields to be used for query of TournamentNews-object for given IDs. */
   public static function build_query_sql( $tnews_id=0, $tid=0 )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_NEWS']->newQuerySQL('TN');
      $qsql->add_part( SQLP_FIELDS,
         'TN.uid AS TNP_ID',
         'TNP.Name AS TNP_Name',
         'TNP.Handle AS TNP_Handle' );
      $qsql->add_part( SQLP_FROM,
         'INNER JOIN Players AS TNP ON TNP.ID=TN.uid' );
      if( $tnews_id > 0 )
         $qsql->add_part( SQLP_WHERE, "TN.ID=$tnews_id" );
      if( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TN.tid=$tid" );
      return $qsql;
   }

   /*!
    * \brief Returns QuerySQL with restrictions to view tournament-news to what user is allowed to view.
    * \param $tnews_id TournamentNews.ID, can be 0
    * \param $tid Tournament.ID, can be 0
    * \param $tnews_status select on this TournamentNews.Status, null to find all (according to user-rights)
    * \param $is_admin true, if user is TD/T-owner/T-admin; false = normal user
    * \param $is_tparticipant true, if user is TP
    */
   public static function build_view_query_sql( $tnews_id, $tid, $tnews_status, $is_admin, $is_tparticipant )
   {
      $qsql = new QuerySQL();
      if( $tnews_id > 0 )
         $qsql->add_part( SQLP_WHERE, "TN.ID=$tnews_id" );
      if( $tid > 0 )
         $qsql->add_part( SQLP_WHERE, "TN.tid=$tid" );
      if( !$is_admin ) // hide some news for non-TDs / non-TPs
      {
         $qsql->add_part( SQLP_WHERE,
            "TN.Status IN ('".TNEWS_STATUS_SHOW."','".TNEWS_STATUS_ARCHIVE."')",
            "(TN.Flags & ".TNEWS_FLAG_HIDDEN.") = 0" );
         if( !$is_tparticipant )
            $qsql->add_part( SQLP_WHERE, "(TN.Flags & ".TNEWS_FLAG_PRIVATE.") = 0" );
      }
      if( !is_null($tnews_status) )
         $qsql->add_part( SQLP_WHERE, "TN.Status='$tnews_status'" );
      return $qsql;
   }//build_view_query_sql

   /*! \brief Returns TournamentNews-object created from specified (db-)row. */
   public static function new_from_row( $row )
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

   /*! \brief Loads and returns TournamentNews-object for given tournament-news-QuerySQL; NULL if nothing found. */
   public static function load_tournament_news_entry_by_query( $qsql )
   {
      $qsql->add_part( SQLP_LIMIT, '1' );
      $row = mysql_single_fetch( "TournamentNews.load_tournament_news_entry_by_query()",
         $qsql->get_select() );
      return ( $row ) ? self::new_from_row( $row ) : NULL;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentNews-objects of given tournament. */
   public static function load_tournament_news( $iterator, $tid )
   {
      $qsql = self::build_query_sql( 0, $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentNews.load_tournament_news($tid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = self::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_news

   /*! \brief Deletes all tournament-news on DELETE-status older than given number days (can be negative). */
   public static function process_tournament_news_deleted( $days_age )
   {
      global $NOW;
      if( !is_numeric($days_age) )
         error('invalid_args', "TournamentNews:process_tournament_news_deleted($days_age)");

      $query = "FROM TournamentNews WHERE Status='".TNEWS_STATUS_DELETE."' AND " .
            "Lastchanged < FROM_UNIXTIME($NOW) - INTERVAL $days_age DAY";

      ta_begin();
      {//HOT-section to delete old tournament-news
         // find tournament-id to clear cache for
         $arr_tids = array();
         $result = db_query( "TournamentNews:process_tournament_news_deleted.find_tourney($days_age)",
            "SELECT tid $query" );
         while( $row = mysql_fetch_array($result) )
            $arr_tids[] = $row['tid'];
         mysql_free_result($result);

         db_query( "TournamentNews:process_tournament_news_deleted($days_age)", "DELETE $query" );
         foreach( $arr_tids as $tid )
            self::delete_cache_tournament_news( 'TournamentNews:process_tournament_news_deleted', $tid );
      }
      ta_end();
   }//process_tournament_news_deleted

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status=null )
   {
      // lazy-init of texts
      if( !isset(self::$ARR_TNEWS_TEXTS['STATUS']) )
      {
         $arr = array();
         $arr[TNEWS_STATUS_NEW]     = T_('New#TN_status');
         $arr[TNEWS_STATUS_SHOW]    = T_('Show#TN_status');
         $arr[TNEWS_STATUS_ARCHIVE] = T_('Archive#TN_status');
         $arr[TNEWS_STATUS_DELETE]  = T_('Delete#TN_status');
         self::$ARR_TNEWS_TEXTS['STATUS'] = $arr;
      }

      $key = 'STATUS';
      if( is_null($status) )
         return self::$ARR_TNEWS_TEXTS[$key];

      if( !isset(self::$ARR_TNEWS_TEXTS[$key][$status]) )
         error('invalid_args', "TournamentNews:getStatusText($status,$key)");
      return self::$ARR_TNEWS_TEXTS[$key][$status];
   }//getStatusText

   /*! \brief Returns flags-text for given int-bitmask or all flags-texts (if arg=null). */
   public static function getFlagsText( $flags=null )
   {
      // lazy-init of texts
      if( !isset(self::$ARR_TNEWS_TEXTS['FLAGS']) )
      {
         $arr = array();
         $arr[TNEWS_FLAG_HIDDEN]    = T_('Hidden#TN_flag');
         $arr[TNEWS_FLAG_PRIVATE]   = T_('Private#TN_flag');
         self::$ARR_TNEWS_TEXTS['FLAGS'] = $arr;
      }
      else
         $arr = self::$ARR_TNEWS_TEXTS['FLAGS'];
      if( is_null($flags) )
         return $arr;

      $out = array();
      foreach( $arr as $flagmask => $flagtext )
         if( $flags & $flagmask ) $out[] = $flagtext;
      return implode(', ', $out);
   }//getFlagsText

   /*! \brief Prints formatted tournament-news with CSS-style with title, publish-date, author and text. */
   public static function build_tournament_news( $tnews )
   {
      $title = make_html_safe($tnews->Subject, true);
      $text = make_html_safe($tnews->Text, true);

      $fout = array();
      if( $tnews->Flags & TNEWS_FLAG_HIDDEN )
         $fout[] = self::getFlagsText(TNEWS_FLAG_HIDDEN);
      if( $tnews->Flags & TNEWS_FLAG_PRIVATE )
         $fout[] = self::getFlagsText(TNEWS_FLAG_PRIVATE);
      $publish_text = ( count($fout) ) ? span('TNewsFlags', implode(', ', $fout), '(%s) ') : '';

      $publish_text .= sprintf( T_('[%s] by %s#tnews_publish'),
         date(DATE_FMT2, $tnews->Published), $tnews->User->user_reference() );

      return
         "<div class=\"TournamentNews\">\n" .
            "<div class=\"Title\">$title</div>" .
            "<div class=\"Published\">$publish_text</div>\n" .
            "<div class=\"Text\">$text</div>" .
         "</div>\n";
   }//build_tournament_news

   public static function delete_cache_tournament_news( $dbgmsg, $tid )
   {
      DgsCache::delete_group( $dbgmsg, CACHE_GRP_TNEWS, "TNews.$tid" );
   }

} // end of 'TournamentNews'

?>
