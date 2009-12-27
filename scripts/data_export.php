<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

//
// NOTE: this script is adjusted to DGS, but phpMyAdmin may be a help too!!
//


chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
//require_once( "include/form_functions.php" );


$defs_orig = (int)(bool)get_request_arg('defs_orig',0);
$defs_sort = (int)(bool)get_request_arg('defs_sort',0);

define('DEFINITION_ORIG', 0 xor $defs_orig);

// new-style dump
define('QUOTE_NAME', 1);
define('CREATE_TIME', 1);
define('IF_NOT_EXISTS', 1);
define('DEFINITION_SORT', 0 xor $defs_sort);

define('DROP_TABLE', 0);
define('AUTO_INCREMENT', 0);
define('CREATE_OPTION', 1);

define('COMMENT_LINE_STR', '--');

//define('CR', chr(13).chr(10));
define('CR', chr(10));

define('QUOTE', '`'); //backquote
//define('QUOTE', "'");

define('CRINDENT', CR.'   ');
define('BLOCKBEG', CR.COMMENT_LINE_STR.' {'.CR);
define('BLOCKEND', CR.COMMENT_LINE_STR.' }'.CR);


function safe_value( $val=NULL)
{
   if( $val === NULL )
      $val = 'NULL';
   else if( !is_numeric($val) )
      $val = "'".mysql_addslashes($val)."'";
   return $val;
} //safe_value

function insert_set( $table, $query, $title=false)
{
   if( $title === '' )
      $title = "Dumping data for table ".quoteit( $table, QUOTE);

   $result = mysql_query( $query) or die(mysql_error());

   $mysqlerror = @mysql_error();
   if( $mysqlerror )
   {
      echo "<p>Error: ".textarea_safe($mysqlerror)."</p>";
      return -1;
   }

   if( !$result )
      return 0;

   $numrows = @mysql_num_rows($result);
   if( $numrows<=0 )
   {
      @mysql_free_result( $result);
      return 0;
   }

   $text = '';
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $str = '';
      $sep = '';
      foreach( $row as $key => $val )
      {
         $str.= $sep.$key.'='.safe_value($val);
         $sep = ',';
      }
      if( $str )
         $text.= "INSERT INTO "
            . quoteit( $table, ( QUOTE_NAME ? QUOTE :'') )
            . " SET $str;" .CR;
   }
   mysql_free_result($result);

   if( $title !== false )
      $text = comment_block( $title).CR.$text;

   return $text;
} //insert_set

function insert_values( $table, $names, $query, $title=false)
{
   if( $title === '' )
      $title = "Dumping data for table ".quoteit( $table, QUOTE);

   $result = mysql_query( $query) or die(mysql_error());

   $mysqlerror = @mysql_error();
   if( $mysqlerror )
   {
      echo "<p>Error: ".textarea_safe($mysqlerror)."</p>";
      return -1;
   }

   if( !$result )
      return 0;

   $numrows = @mysql_num_rows($result);
   if( $numrows<=0 )
   {
      @mysql_free_result( $result);
      return 0;
   }

   // NOTE: OLD_STYLE_DUMP (removed now) does not ensure that some columns are not swapped.
   // old-style: INSERT INTO TranslationTexts VALUES (5,'Move outside board?',NULL,'Y');
   // new-style: (5,'Move outside board?',NULL,'Y'),
   $rowbeg = CRINDENT."(";
   $rowend = "),";

   $hdrs = explode(',',$names);
   $text = '';
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $str = '';
      $sep = '';
      foreach( $hdrs as $key )
      {
         $str.= $sep.safe_value(@$row[$key]);
         $sep = ',';
      }
      if( $str )
      {
         $text.= $rowbeg.$str.$rowend;
      }
   }
   mysql_free_result($result);

/* new-style:
INSERT INTO TranslationTexts
      (ID,Text,Ref_ID,Translatable) VALUES
      (5,'Move outside board?',0,'Y'),
      (...);
*/
   if( $text )
      $text = CR."INSERT INTO "
         . quoteit( $table, ( QUOTE_NAME ? QUOTE :'') )
         . CRINDENT."($names) VALUES"
         . substr( $text, 0, -1) .";";

   if( $title !== false )
      $text = comment_block( $title).$text.CR;

   return $text;
} //insert_values

function multi_insert_values( $tables, $title=false, $rowmod=0)
{
   $text = '';
   foreach( $tables as $table => $clauses )
   {
      $rowmod = ($rowmod % LIST_ROWS_MODULO)+1;
      @list($fields, $where, $order) = $clauses;
      if( $where )
         $where = ' WHERE '.$where;
      else
         $where = '';
      if( $order )
         $order = ' ORDER BY '.$order;
      else
         $order = '';

      $str = insert_values( $table
            , $fields
            , "SELECT $fields FROM $table$where$order"
            , $title
            );

      $text.= echoTR( "Row$rowmod", $str);
   }
   return $text;
} //multi_insert_values

function after_table( $table)
{
   global $dumptype;
   $str = '';

   if( (string)$dumptype == 'init' )
   {
      switch((string)$table)
      {
         case 'Players': //'Statistics':
/*
            global $GUESTPASS;
            $str = insert_set( 'Players'
               , "SELECT Handle,Name"
//                .",\"".PASSWORD_ENCRYPT."('$GUESTPASS')\" as Password"
               .",Password FROM Players WHERE Handle='guest'"
               , 'Insert Guest account' );
*/
            global $GUESTPASS;
            $str = comment_block('Insert Guest account')
               .CR."INSERT INTO Players SET"
               .CRINDENT."ID=1,"
               .CRINDENT."Handle=".safe_value('guest').","
               .CRINDENT."Name=".safe_value('Guest').","
               .CRINDENT."Password=".PASSWORD_ENCRYPT."(".safe_value($GUESTPASS).");"
               .CR
               .CR."INSERT INTO ConfigPages SET User_ID=1 ;"
               .CR."INSERT INTO ConfigBoard SET User_ID=1 ;"
               .CR."INSERT INTO UserQuota SET uid=1 ;"
               .CR;
            break;

         case 'Clock':
            $str = insert_set( 'Clock',
               "SELECT ID FROM Clock WHERE ID>=0 AND MOD(ID,100)<24 AND ID<200 ORDER BY ID", '' );
            $str.= insert_set( 'Clock',
               "SELECT ID, 0 as Lastchanged FROM Clock WHERE ID>200 AND ID<204 ORDER BY ID" );
            break;
      } //switch($table)
   }

   return $str;
} //after_table


function get_tables( $database)
{
   global $dumptype;

   if( $dumptype == 'init' )
   {
      $tables = array (
            'Adminlog',
            'Bio',
            'Clock',
            'ConfigBoard',
            'ConfigPages',
            'Contacts',
            'Errorlog',
            'FAQ',
            'FAQlog',
            'FeatureList',
            'FeatureVote',
            'Folders',
            'ForumRead',
            'Forumlog',
            'Forumreads', //FIXME not used since DGS 1.0.15-release
            'Forums',
            'Games',
            'GamesNotes',
            'GoDiagrams',
            'MessageCorrespondents',
            'Messages',
            'MoveMessages',
            'Moves',
            'Observers',
            'Players',
            'Posts',
            'Profiles',
            'RatingChange', // only used in "old" update_rating()-func, but keeping for now
            'Ratinglog',
            'Statistics',
            'Tournament',
            'TournamentDirector',
            'TournamentParticipants',
            'TournamentProperties',
            //'TournamentRound',  // not yet there
            'TournamentRules',
            'TranslationFoundInGroup',
            'TranslationGroups',
            'TranslationLanguages',
            'TranslationPages',
            'TranslationTexts',
            'Translationlog',
            'Translations',
            'UserQuota',
            'Waitingroom',
            'WaitingroomJoined',
         );
   }
   else
   {
      $result = mysql_query( 'SHOW TABLES FROM ' . quoteit( $database) )
            or die(mysql_error());

      $mysqlerror = @mysql_error();
      if( $mysqlerror )
      {
         echo "<p>Error: ".textarea_safe($mysqlerror)."</p>";
         return -1;
      }

      $tables = array();
      while( list($row) = mysql_fetch_row( $result ) )
         $tables[] = $row;
      mysql_free_result($result);
      sort( $tables);
   }

   return $tables;
} //get_tables


// definitions_fix -------------
// hide some variations between MySQL or DGS versions.
$defs_bef= array(); // defs_bef|aft[table][field1] = field2  (field1 before|after field2)
$defs_aft= array();
$defs_rep= array(); // repair defs_rep[table][field][regex] = replacement

//those are the default values of older versions
// Date timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
// Date timestamp(14) NOT NULL,
foreach( array(
   'Adminlog',
   'Errorlog',
   'Translationlog',
   'Forumlog',
) as $table ) {
   $defs_rep[$table]['Date']
      ['%\s+default\s+CURRENT_TIMESTAMP\s+on\s+update\s+CURRENT_TIMESTAMP\b%is'] = '';
   $defs_rep[$table]['Date']
      ['%timestamp(\s*[^\(])%is'] = 'timestamp(14)\\1';
   //$defs_bef[$table]['Date'] = 'PRIMARY';
}
// PosIndex varchar(80) character set latin1 collate latin1_bin NOT NULL default '',
// PosIndex varchar(80) binary NOT NULL default '',
$defs_rep['*']['*']
   ['%\s+character\s+set\s+latin1\s+collate\s+latin1_bin\b%is'] = ' binary';
// Handle varchar(16) character set latin1 NOT NULL default '',
$defs_rep['*']['*']
   ['%\s+character\s+set\s+latin1\b%is'] = '';


// correct field-order
/* FIXME not needed(?) after DB-sync for DGS 1.0.15 release
//$defs_{move}[{table}][{src}] = {dst};
switch( (string)FRIENDLY_SHORT_NAME )
{
   case 'dDGS':
      //$defs_aft['GoDiagrams']['Date'] = 'SGF';
      //$defs_aft['Adminlog']['IP'] = 'Date';
      //$defs_aft['Errorlog']['IP'] = 'Date';
      break;

   case 'DGS':
      //$defs_???['Messages']['To_ID'] = ''; //still exist in DGS
      //$defs_???['Messages']['From_ID'] = ''; //still exist in DGS
      //$defs_aft['Players']['MayPostOnForum'] = 'Adminlevel';
      //$defs_aft['Players']['Rating2'] = 'Rating';
      //$defs_bef['ConfigPages']['StatusFolders'] = 'Running';
      //$defs_aft['Waitingroom']['Handicap'] = 'Komi';
      break;
} //FRIENDLY_SHORT_NAME
//$defs_aft['GamesNotes']['Notes'] = 'Hidden';
//$defs_bef['GamesNotes']['Notes'] = 'PRIMARY';
*/


function definitions_fix( $table, $keys)
{
   global $defs_bef, $defs_aft, $defs_rep;

   //move columns
   $ary = array();
   foreach( $keys as $str )
   {
      $row = def_split($str);
      $name = $row[0];
      $ary[$name][]= $str;
   }
   foreach( array('defs_bef','defs_aft') as $mov )
   {
      if( isset(${$mov}[$table]) )
      {
         foreach( ${$mov}[$table] as $src => $dst )
         {
            $keys = array();
            $tmp = 0;
            foreach( $ary as $name => $row )
            {
               if( $name == $src )
                  continue;
               if( $name != $dst )
               {
                  $keys[$name]= $row;
                  continue;
               }
               $tmp = 1;
               if( $mov == 'defs_bef' )
               {
                  $keys[$src]= $ary[$src];
                  $keys[$name]= $row;
               }
               else
               {
                  $keys[$name]= $row;
                  $keys[$src]= $ary[$src];
               }
            }
            if( $tmp )
               $ary = $keys;
         }
      }
   }
   $keys = array();
   foreach( $ary as $name => $row )
   {
      foreach( $row as $str )
      {
         foreach( array($table,'*') as $tbl )
         {
            foreach( array($name,'*') as $nam )
            {
               if( isset($defs_rep[$tbl]) && isset($defs_rep[$tbl][$nam]) )
               {
                  foreach( $defs_rep[$tbl][$nam] as $rgx => $rep )
                     $str= preg_replace($rgx, $rep, $str);
               }
            }
         }
         $keys[]= $str;
      }
   }
   //end move columns

   //misc adjusts
   $defs = array();
   foreach( $keys as $str )
   {
      $row = def_split($str);
      $name = $row[0];

      /*
      if( (string)$table == 'Posts' )
      {
         if( (string)$name == 'KEY' ) //can't have this option with older versions
         {
            if( $row[1] == 'SomeFieldName' )
               $str = eregi_replace('Time DESC','Time',$str); // do something
         }
      }
      */
      if( $str )
         $defs[] = $str;
   }
   return $defs;
}

function quoteit( $mixed, $quote='`')
{
   if( is_array( $mixed) )
   {
      $result = array();
      foreach( $mixed AS $key => $val)
         $result[$key] = quoteit( $val);
      return $result;
   }
   if( !empty($mixed) || is_numeric($mixed) )
      return $quote . trim($mixed, " '`$quote") . $quote;
   return $mixed;
} //quoteit

function adj_eol( $str, $cr=CR, $trim=' ')
{
   //if( $cr===false ) $cr = CR;
   return ereg_replace(
         "[$trim\x01-\x1f]*[\x0a\x0d]+[$trim\x01-\x1f]*",
         $cr,
         $str );
} //adj_eol

function def_split( $str)
{
   return preg_split('%[\s,\'`]+%', $str, -1, PREG_SPLIT_NO_EMPTY);
} //def_split

function fdate( $sdat)
{
   $fmt= 'Y-m-d H:i:s \G\M\T'; //O e

   if( is_string( $sdat) )
      $sdat = strtotime($sdat);
   return date( $fmt, $sdat); //date gmdate
} //fdate

define('HTML_PRE', 1);
function dump2html( $str)
{
   // NOTE: default without charset doesn't work for TranslationsTexts somehow (must be some strange char in it)
   //$str = textarea_safe( $str ); TODO
   $str = textarea_safe( $str, 'ISO-8859-1' );

   if( HTML_PRE )
   {
      $str = trim( $str);
   }
   else
   {
      $str = str_replace(' ', '&nbsp;', $str); //&nbsp;&deg;
      $str = str_replace(CR, "<br>\n", $str);
   }
   return $str;
} //dump2html

function echoTR( $typ, $str)
{
   global $export_it;

   if( !$str)
      return $str;

   if( $export_it )
      return BLOCKBEG. trim($str) .BLOCKEND;

   switch((string)$typ)
   {
      case 'th':
      case 'td':
         if( HTML_PRE )
            $str= "<pre>\n" . dump2html( $str) . "\n</pre>";
         else
            $str= "<br>\n" . dump2html( $str) . "\n<br>";
         $str= "<tr>\n<$typ nowrap>" . $str . "</$typ>\n</tr>\n";
         break;

      default:
         if( HTML_PRE )
            $str= "<pre>\n" . dump2html( $str) . "\n</pre>";
         else
            $str= "<br>\n" . dump2html( $str) . "\n<br>";
         $str= "<tr class=\"$typ\" ondblclick=\"toggle_class(this,'$typ','Hil$typ')\">\n"
            . "<td nowrap>" . $str . "</td>\n</tr>\n";
         break;
   }//switch $typ

   return $str;
} //echoTR


function comment_start()
{
   return COMMENT_LINE_STR . CR;
}

function comment_end()
{
   return COMMENT_LINE_STR . CR;
}

function comment_line( $str)
{
   return COMMENT_LINE_STR . ' ' . trim($str) . CR;
}

function comment_block( $str)
{
   return COMMENT_LINE_STR
         . adj_eol( chr(10).trim($str), CR.COMMENT_LINE_STR.' ')
         . CR . COMMENT_LINE_STR . CR
         ;
}

class dbTable
{
   var $qpath;
   var $qdatabase;
   var $qname;
   var $uname;
   var $xname;
   var $engine;

   function dbTable( $database, $name)
   {
      $this->qdatabase = quoteit( $database);
      $this->qname = quoteit( $name);
      $this->qpath = $this->qdatabase . '.' . $this->qname;
      $this->uname = substr( $this->qname, 1, -1);

      $this->xname = quoteit( $name, ( QUOTE_NAME ? QUOTE :'') );

      $this->engine = '';
   }

   function structure()
   {
      $comment = '';
      $head = '';
      $body = '';
      $incr = '';
      $opts = '';
      $ok = 0;

      $query = 'SHOW TABLE STATUS FROM ' . $this->qdatabase
             . ' LIKE \'' . $this->uname . '\'';
      if( $row=mysql_single_fetch( false, $query) )
      {

         if( !@$row['Engine'] && @$row['Type'] )
            $row['Engine']= $row['Type'];
         if( @$row['Engine'] )
         {
            $ok = 1;
            $this->engine = $row['Engine'];
            if( AUTO_INCREMENT && @$row['Auto_increment'] )
               $incr = ' AUTO_INCREMENT=' . $row['Auto_increment'];

            if( CREATE_TIME && @$row['Create_time'] ) //also 'Update_time'
               $comment .= 'Created: '.fdate( $row['Create_time']).chr(10);

            if( CREATE_OPTION && @$row['Create_options'] )
            {
               $opts = $row['Create_options'];
               $opts = strtoupper(trim($opts));
               if( $opts )
                  $opts = ' ' . $opts;
            }
         }
      }

      if( !$ok )
      {
         $comment.= 'Not found.'.chr(10);
         if( @$GLOBALS['Super_admin'] )
         {
            $comment.= "QUERY: ".$query.chr(10);
         }
      }

      $struct = //CR.
           comment_block( 'Table structure for table '.quoteit( $this->uname, QUOTE)
               .chr(10).$comment);

      if( $ok )
      {
         if( DROP_TABLE )
            $head.= 'DROP TABLE IF EXISTS '.$this->qname.';'.CR;

         mysql_query('SET SQL_QUOTE_SHOW_CREATE='
            . ( QUOTE_NAME ?'1' :'0') ) or die(mysql_error());

         if( !($body = $this->structure_body()) )
            $body = 'Error: body';

         $struct.= CR.
              $head
            . 'CREATE TABLE '
               . ( IF_NOT_EXISTS ?'IF NOT EXISTS ' :'' )
               . $this->xname.' ('.CR
            . $body
            . ') ENGINE='.$this->engine.$opts.$incr.';'.CR
            ;
      }
      return $struct;
   }

   var $keywords = array(
      'PRIMARY'   => 1, // KEY (index_col_name,...)
      'KEY'       => 2, // [index_name] (index_col_name,...)
      'INDEX'     => 3, // [index_name] (index_col_name,...)
      'FULLTEXT'  => 4, // [INDEX] [index_name] (index_col_name,...)
      'UNIQUE'    => 5, // [INDEX] [index_name] (index_col_name,...)
      'CHECK'     => 6, // (expr)
      ) ;

   function structure_body()
   {
      $body = '';
      if( $row=mysql_single_fetch( false,
             'SHOW CREATE TABLE ' . $this->qpath
           , FETCHTYPE_ARRAY) )
      {
         if( !($str=@$row['Create Table']) )
            $str = @$row[1];
         if( !$str ) return '';

         //find the inner text of external parentheses
         if( preg_match( '%^[^\\(]*\\((.*)\\)[^\\)]*$%s'
                       , $str, $row) <= 0 )
            return '';

         $str = trim( preg_replace('%[\t ]+%', ' '
                     , adj_eol(@$row[1], chr(10))));
         if( !$str ) return '';

         $keys = explode( chr(10), $str);
         if( !DEFINITION_ORIG )
            $keys = definitions_fix( $this->uname, $keys);
         $defs = array();
         if( DEFINITION_SORT )
         {
            $ary = array();
            $spc = array();
            foreach( $keys as $str )
            {
               $row = explode(' ', $str, 2); //def_split($str);
               if( array_key_exists( @$row[0], $this->keywords) )
                  $ary[$str] = $this->keywords[$row[0]].$str;
               else if( eregi('auto_increment', $str) )//( @$row[0] == 'ID' )
                  $defs[] = $str;
               else
                  $spc[] = $str;
            }
            if( DEFINITION_SORT )
            {
               asort($defs);
               asort($spc);
               asort($ary);
            } //DEFINITION_SORT

            $defs = array_merge($defs, $spc);
            $keys = array_keys($ary);
         } //DEFINITION_SORT

         $spc = '   ';
         $str = '';
         foreach( array('defs','keys') as $ary )
            if( count($$ary) )
         {
            $$ary = implode(CR.$spc, $$ary);
            if( !QUOTE_NAME )
               $$ary = str_replace('`', '', $$ary);
               //$$ary = ereg_replace("['`]+", '', $$ary);
            $str.= $spc.$$ary.CR;
            //$str.= comment_line( '----')
         }

         $body.= $str;
      }
      return $body;
   }
}// end of class 'dbTable'


function dump_header( $database)
{
   global $NOW;

   $str = FRIENDLY_LONG_NAME." dump".chr(10);
   $str.= "Host: ".@$_SERVER['HTTP_HOST'].chr(10);
   $str.= "Database: ".$database.chr(10);
   $str.= "Generation Time: ".fdate( $NOW).chr(10);
   $str.= "Server version: ".@$_SERVER['SERVER_SOFTWARE'].chr(10);
   $str.= "PHP version: ".@phpversion().chr(10);
   $str.= sprintf("MySQL version: %s (%s)",READ_MYSQL_VERSION,MYSQL_VERSION).chr(10);

   if( 0 && @$GLOBALS['Super_admin'] )
   {
      $str.= "MYSQLUSER: ".@$GLOBALS['MYSQLUSER'].chr(10);
      $str.= "MYSQLPASSWORD: ".@$GLOBALS['MYSQLPASSWORD'].chr(10);
   }

   return comment_block( $str);
} //dump_header

function init_dump( $database)
{
   $tables = get_tables( $database);
   if( !is_array($tables) )
      return '';

   asort($tables);

   $text = dump_header( $database);
   $text = echoTR( 'th', $text);

   $c=0;
   foreach( $tables as $table)
   {
      $c=($c % LIST_ROWS_MODULO)+1;
      $tbl = new dbTable( $database, $table);
      $str = $tbl->structure();

      $text.= echoTR( "Row$c", $str);

      $str = after_table( $table);
      $text.= echoTR( 'td', $str);
   }

   return $text;
} //init_dump

function transl_dump( $database)
{
   $text = dump_header( $database);
   $text = echoTR( 'th', $text);

/*
#
# Dumping data for table 'TranslationTexts'
#
INSERT INTO TranslationTexts VALUES (1,'Sorry, you may not pass before all handicap stones are placed.',0,'Done');
ID,Text,Ref_ID,Translatable
#
# Dumping data for table 'Translations'
#
INSERT INTO Translations VALUES (96,1,'Admin');
Original_ID,Language_ID,Text
#
# Dumping data for table 'TranslationLanguages'
#
INSERT INTO TranslationLanguages VALUES (1,'sv.iso-8859-1','Swedish');
ID,Language,Name
#
# Dumping data for table 'TranslationGroups'
#
INSERT INTO TranslationGroups VALUES (1,'Common');
ID,Groupname
#
# Dumping data for table 'TranslationFoundInGroup'
#
INSERT INTO TranslationFoundInGroup VALUES (1,8);
Text_ID,Group_ID
#
# Dumping data for table 'TranslationPages'
#
INSERT INTO TranslationPages VALUES (3,'error.php',8);
ID,Page,Group_ID
*/
   $tables = array(
      // table => array( fields, where, order )
      'TranslationGroups'
         => array('ID,Groupname','','ID'),
      'TranslationPages'
         => array('Page,Group_ID','', 'Group_ID,Page'),
      'TranslationTexts' //Originals
         => array('ID,Text,Ref_ID,Translatable','','ID'),
      'TranslationFoundInGroup'
         => array('Text_ID,Group_ID','','Text_ID,Group_ID'),
/**
 * TODO: insert at least english translations? (Cf language_dump())
 * either, here:
      $langID = ID_of('en.iso-8859-1'); //which is not 1, actually
      'TranslationLanguages'
         => array('ID,Language,Name',"ID=$langID", 'ID'),
      'Translations' //better to split it in different files
         => array('Language_ID,Original_ID,Text',"Language_ID=$langID",'Language_ID,Original_ID'),
 * or, farther:
      $text.= language_dump( $database, 'en.iso-8859-1', false);
 **/
      );

   $text.= multi_insert_values( $tables, '');

   return $text;
} //transl_dump

function language_dump( $database, $lang, $header=true)
{
   global $lang_desc;

   if( $header === true )
      $text = dump_header( $database);
   else if( is_string($header) )
      $text = trim($header);
   else
      $text = '';
   if( $text )
      $text = echoTR( 'th', $text);

   $langname = @$lang_desc[$lang];
   //@list( $browsercode, $charenc) = explode( LANG_CHARSET_CHAR, $lang, 2);

   $title = "Datas for language: $langname ($lang)";

   $query = "SELECT ID FROM TranslationLanguages WHERE Language='$lang'";
   if( ($row=mysql_single_fetch( false, $query))
         && @$row['ID'] > 0 )
      $langID = $row['ID'];
   else
   {
      $str = $title.chr(10).'Not found.';
      $str = comment_block( $str);
      $text.= echoTR( "Row$c", $str);
      return $text;
   }

   $tables = array(
      'TranslationLanguages'
         => array('ID,Language,Name',"ID=$langID", 'ID'),
      'Translations' //better to split it in different files
         => array('Language_ID,Original_ID,Text',"Language_ID=$langID",'Language_ID,Original_ID'),
   );

   $text.= multi_insert_values( $tables, $title);

   return $text;
} //language_dump

function freesql_dump( $database, $query)
{
   $title = "Free SQL: ".$query.';';

   $result = mysql_query( $query);

   $mysqlerror = @mysql_error();
   if( $mysqlerror )
   {
      $title .= chr(10)."Error: ".textarea_safe($mysqlerror);
      return echoTR('th', comment_block( $title));
   }

   if( !$result )
   {
      $title .= chr(10)."Error: Fail";
      return echoTR('th', comment_block( $title));
   }

   $numrows = @mysql_num_rows($result);
   $title .= chr(10)." => $numrows rows";
   if( $numrows<=0 )
   {
      @mysql_free_result( $result);
      return echoTR('th', comment_block( $title));
   }

   $hdrs = NULL;
   $col = 0;
   $text = '';
   $n=0;
   $c=0;
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $n++;
      $c=($c % LIST_ROWS_MODULO)+1;

      if( !isset($hdrs) )
      {
         $hdrs = array_keys( $row);
         $col = count($hdrs);
         $str = implode('</th><th>',$hdrs);
         $text.= "<tr><th>".$str.'</th></tr>'.CR;
      }

      $str = '';
      $sep = '';
      $rnam= '';
      foreach( $hdrs as $key )
      {
         if( !$rnam )
            $rnam= basic_safe(trim(@$row[$key]));
         $str.= $sep.safe_value(@$row[$key]);
         $sep = '</td><td>';
      }
      $rnam= "Row$n".($rnam ?'-'.$rnam :'');
      $text.= "<tr class=Row$c title='$rnam'><td>".$str.'</td></tr>'.CR;
   }
   mysql_free_result($result);

   if( $title !== false )
      $text = "<tr><td colspan=$col><pre>"
         .dump2html(comment_block( $title))
         .'</pre></td></tr>'.CR.$text;

   return $text;
} //freesql_dump


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   setTZ('GMT');

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'date_export');

   if( is_array($ARR_USERS_MAINTENANCE) && in_array( $player_row['Handle'], $ARR_USERS_MAINTENANCE ) )
      $Super_admin = true;
   else
      $Super_admin = false;

   //FIXME UTF-8 makes problems, see dump2html()-func !!
   $encoding_used= get_request_arg( 'charset', 'UTF-8'); //LANG_DEF_CHARSET iso-8859-1 UTF-8

   if( $row=mysql_single_fetch( false, 'SELECT VERSION() AS version') )
   {
      define('READ_MYSQL_VERSION', $row['version']);
/*
      $row = explode('.', READ_MYSQL_VERSION);
      define('READ_MYSQL_VERSION_INT', (int)sprintf('%d%02d%02d'
                  , $row[0], $row[1], intval($row[2])));
*/
   } else {
      define('READ_MYSQL_VERSION', '5.0.38');
/*
      define('READ_MYSQL_VERSION_INT', 5038);
*/
   }


   $lang_desc = get_language_descriptions_translated( true);
   ksort($lang_desc);

   $dumptypes = array(
         '' => '',
         'init' => 'init.mysql',
         'transl' => 'translationdata.mysql',
      );
   foreach( $lang_desc as $lang => $langname )
   {
      $dumptypes['lang.'.$lang] = "translationdata.$lang.mysql";
   }

   $dumptype = trim(get_request_arg('dumptype'));
   if( !array_key_exists( $dumptype, $dumptypes) )
      $dumptype= '';

   $show_it= @$_REQUEST['show_it'];
   $export_it= @$_REQUEST['export_it'];

   $freesql_it= @$_REQUEST['freesql_it'];
   $freesql= trim(get_request_arg('freesql'));

   //====================

   $row = explode('.', $dumptype, 2);
   $export_file = $dumptypes[$dumptype];
   $text = '';
   if( @$GLOBALS['Super_admin'] && $freesql_it && $freesql )
   {
      $text = freesql_dump( DB_NAME, $freesql);
      $show_it = true;
      $dumptype = 'freeSQL';
      $export_file= 'db_export.mysql';
   }
   else
   {
      switch( (string)$row[0] )
      {
         case 'init':
            $text = init_dump( DB_NAME);
            break; //'init'
         case 'transl':
            $text = transl_dump( DB_NAME);
            break; //'transl'
         case 'lang':
            $text = language_dump( DB_NAME, $row[1]);
            break; //'lang'
      }//switch $row[0]
   }


   //====================

   if( $export_it && $text && $export_file )
   {
      $export_file= FRIENDLY_SHORT_NAME.'-'.$export_file;

      // this one open the text/plain in the browser by default
      //header( 'Content-type: text/plain' ); //; charset=iso-8859-1' ); //$encoding_used
      // this one exist and put a costume of binary on the text
      //header( 'Content-type: application/octet-stream' );
      // this last does not exist but it force the "record to disk"
      header( 'Content-type: application/x-mysql' );
      header( "Content-Disposition: inline; filename=\"$export_file\"" );
      header( "Content-Description: PHP Generated Data" );

      //to allow some mime applications to find it in the cache
      header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*60));
      header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));

      echo $text;
      exit;
   } //$export_it


   //====================

   start_html( 'data_export', 0, '', //@$player_row['SkinName'],
      "  table.Table { border:0; background:#c0c0c0; text-align:left;\n" .
      "   border-spacing:1px; border-collapse:separate; margin:0.5em 2px; }\n" .
      "  table.Table td, table.Table th { padding:4px; }\n" .
      "  tr.Row2 { background: #e0e8ed; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.HilRow2 { background: #e7cdb1; }\n" .
      "  tr.HilRow1 { background: #ffdfbf; }" );


   $dform = new Form('dform', 'data_export.php#result', FORM_POST, true );

   //$dform->add_hidden( $key, $val)
   $dform->add_row( array(
      'DESCRIPTION', 'Dump type',
      'SELECTBOX', 'dumptype', 1, $dumptypes, $dumptype, false,
      ) );
   $dform->add_row( array(
      'TAB',
      'CHECKBOX', 'defs_sort', 1, 'Definitions sort&nbsp;', $defs_sort,
      'CHECKBOX', 'defs_orig', 1, 'Original DDL&nbsp;', $defs_orig,
      ));
   $dform->add_row( array(
      'SUBMITBUTTONX', 'show_it', 'Show it [&amp;s]',
               array('accesskey' => 's'),  // keep static acckey
      'SUBMITBUTTONX', 'export_it', 'Download it [&amp;d]',
               array('accesskey' => 'd'),  // keep static acckey
      'HIDDEN', 'charset', $encoding_used,
      ) );

   if( @$GLOBALS['Super_admin'] )
   {
      $dform->add_empty_row();
      $dform->add_row( array(
         'DESCRIPTION', 'free SQL',
         'TEXTAREA', 'freesql', 60, 3, $freesql,
         ) );
      $dform->add_row( array(
         'SUBMITBUTTON', 'freesql_it', 'free SQL',
         ) );
   }

   $dform->echo_string(1);


   //====================

   if( $show_it && $text)
   {
      echo "\n<table class=Table cellpadding=4 cellspacing=1>\n"
         . $text ."</table>\n";

      $hiddens = array( 'dumptype' => $dumptype);
      $dform->get_hiddens( $hiddens);
      $download_uri = make_url( "data_export.php?export_it=1", $hiddens);

      echo "<br>" . anchor( $download_uri, "[ Download it ]"
         , '', array( 'accesskey' => 'd' ) ) . "<br>";  // keep static acckey
   }

   end_html();
}

?>
