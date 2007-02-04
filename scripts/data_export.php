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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
//require_once( "include/form_functions.php" );


define('OLD_STYLE_DUMP', !(bool)@$_REQUEST['new_style']);

if(OLD_STYLE_DUMP){
  define('QUOTE_NAME', 0);
  define('CREATE_TIME', 0);
  define('IF_NOT_EXISTS', 0);
  define('DEFINITION_SORT', 0);
}else{ //OLD_STYLE_DUMP
  define('QUOTE_NAME', 1);
  define('CREATE_TIME', 1);
  define('IF_NOT_EXISTS', 1);
  define('DEFINITION_SORT', 0);
} //OLD_STYLE_DUMP
define('DROP_TABLE', 0);
define('AUTO_INCREMENT', 0);
define('CREATE_OPTION', 1);


//define('COMMENT_LINE_STR', '//');
if(OLD_STYLE_DUMP){
  define('COMMENT_LINE_STR', '#');
}else{ //OLD_STYLE_DUMP
  define('COMMENT_LINE_STR', '--');
} //OLD_STYLE_DUMP

//define('CR', chr(13).chr(10));
//define('CR', chr(13));
define('CR', chr(10));

if(OLD_STYLE_DUMP){
  define('QUOTE', "'");
}else{ //OLD_STYLE_DUMP
  define('QUOTE', '`'); //backquote
  //define('QUOTE', "'");
} //OLD_STYLE_DUMP

define('CRINDENT', CR.'     ');
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

   $result = mysql_query( $query)
            or die(mysql_error());

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

   $result = mysql_query( $query)
            or die(mysql_error());

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

   /**
    * TODO: it would be better to soon switch to the new_style_dump
    * for this function because the OLD_STYLE_DUMP can swap
    * some columns - without any warning - by construction.
    **/
   if( OLD_STYLE_DUMP )
   {
//INSERT INTO TranslationTexts VALUES (5,'Move outside board?',NULL,'Y');
      $rowbeg = CR."INSERT INTO "
         . quoteit( $table, ( QUOTE_NAME ? QUOTE :'') )
         . " VALUES ("
         ;
      $rowend = ");";
   }
   else //OLD_STYLE_DUMP
   {
//       (5,'Move outside board?',NULL,'Y'),
      $rowbeg = CRINDENT."(";
      $rowend = "),";
   }

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

   if( !OLD_STYLE_DUMP )
   {
/*
INSERT INTO TranslationTexts
      (ID,Text,Ref_ID,Translatable) VALUES
      (5,'Move outside board?',NULL,'Y'),
      (...);
*/
      if( $text )
         $text = CR."INSERT INTO "
            . quoteit( $table, ( QUOTE_NAME ? QUOTE :'') )
            . CRINDENT."($names) VALUES"
            . substr( $text, 0, -1) .";";
   }
   
   if( $title !== false )
      $text = comment_block( $title).$text.CR;

   return $text;
} //insert_values

function after_table( $table)
{
 global $dumptype;
   $str = '';

   switch($dumptype)
   {
   case 'init':
      switch($table)
      {
      case 'Players': //'Statistics':
/*
         global $GUESTPASS;
         $str = insert_set( 'Players'
            , "SELECT Handle,Name"
//              .",\"PASSWORD('$GUESTPASS')\" as Password"
              .",Password"
            ." FROM Players"
            ." WHERE Handle='guest'"
            , 'Insert Guest account'
            );
*/
         global $GUESTPASS;
         $str = comment_block('Insert Guest account')
            .CR."INSERT INTO Players SET"
            .CRINDENT."Handle=".safe_value('guest').","
            .CRINDENT."Name=".safe_value('Guest').","
            .CRINDENT."Password=PASSWORD(".safe_value($GUESTPASS).");"
            .CR;
         break;
      case 'Clock':
         $str = insert_set( 'Clock'
            , "SELECT ID"
            ." FROM Clock"
            ." WHERE ID>=0 AND MOD(ID,100)<24 AND ID<200"
            ." ORDER BY ID"
            , ''
            );
         $str.= insert_set( 'Clock'
            , "SELECT ID, 0 as Lastchanged"
            ." FROM Clock"
            ." WHERE ID>200 AND ID<204"
            ." ORDER BY ID"
            );
         break;
      } //switch($table)
   } //switch($dumptype)
   
   return $str;
} //after_table


function get_tables( $database)
{
 global $dumptype;
   switch($dumptype)
   {
   case 'init':
     $tables = array (
         'Players',
         'Games',
         'GamesNotes',
         'Bio',
         'RatingChange',
         'Ratinglog',
         'Statistics',
         'Messages',
         'MessageCorrespondents',
         'Folders',
         'Moves',
         'MoveMessages',
         'Waitingroom',
         'Observers',
         'Tournament',
         'TournamentRound',
         //'Knockout',
         'TournamentOrganizers',
         'TournamentParticipants',
         'Errorlog',
         'Adminlog',
         'Translationlog',
         'TranslationTexts',
         'Translations',
         'TranslationLanguages',
         'TranslationGroups',
         'TranslationFoundInGroup',
         'TranslationPages',
         'Forums',
         'Posts',
         'Forumreads',
         'GoDiagrams',
         'FAQ',
         'FAQlog',
         'Clock',

      );
      break;
   default:
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
      {
         $tables[] = $row;
      }
      mysql_free_result($result);
      sort( $tables);
      break;
   }

   return $tables;
} //get_tables

function quoteit( $mixed, $quote='`')
{
   if( is_array( $mixed) )
   {
      $result = array();
      foreach( $mixed AS $key => $val)
         $result[$key] = quoteit( $val);
      return $result;
   }
   if( !empty($mixed) or is_numeric($mixed) )
      return $quote . trim( $mixed, " '`$quote") . $quote;
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
   $str = textarea_safe( $str);
   if( HTML_PRE )
   {
      $str = trim( $str);
   }
   else
   {
      $str = str_replace( ' ', '&nbsp;', $str); //&nbsp;&deg;
      $str = str_replace( CR, "<br>\n", $str);
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

   switch($typ)
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
      $str= "<tr class=\"$typ\" ondblclick=\"row_click(this,'$typ')\">\n"
         . "<td nowrap>" . $str . "</td>\n</tr>\n";
      break;
   }

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
            . ') TYPE='.$this->engine.$opts.$incr.';'.CR
            ;
      }
      return $struct;
   }

   var $keywords = array(
      'PRIMARY'   => 1, // KEY (index_col_name,...)
      'KEY'       => 2, // [index_name] (index_col_name,...)
      'INDEX'     => 3, // [index_name] (index_col_name,...)
      'UNIQUE'    => 4, // [INDEX] [index_name] (index_col_name,...)
      'FULLTEXT'  => 5, // [INDEX] [index_name] (index_col_name,...)
      'CHECK'     => 6, // (expr)
      ) ;

   function structure_body()
   {
      $body = '';
      if( $row=mysql_single_fetch( false,
             'SHOW CREATE TABLE ' . $this->qpath
           , 'array') )
      {
         if( !($str=@$row['Create Table']) )
            $str = @$row[1];
         if( !$str ) return '';

         if( preg_match( '%^[^\\(]*\\((.*)\\)[^\\)]*$%s'
                       , $str, $row) <= 0 )
            return '';

         $str = trim( adj_eol( @$row[1], chr(10)) );
         if( !$str ) return '';

         $keys = explode( chr(10), $str);
         $defs = array();
         if( DEFINITION_SORT )
         {
            $ary = array();
            foreach( $keys as $str )
            {
               $row = explode( ' ', $str, 2);
               if( array_key_exists( @$row[0], $this->keywords) )
                  $ary[$str] = $this->keywords[$row[0]].$str;
               else
               {
                  $defs[] = $str;
               }
            }
            if( DEFINITION_SORT )
            {
               asort( $defs);
               asort( $ary);
            } //DEFINITION_SORT

            $keys = array_keys( $ary);
         } //DEFINITION_SORT

         $spc = '   ';
         $str = '';
         if( count($defs) )
         {
            $defs = implode( CR.$spc, $defs);
            if( !QUOTE_NAME )
               $defs = str_replace('`', '', $defs);
            $str.= $spc.$defs.CR;
         }
         //$str.= comment_line( '----')
         if( count($keys) )
         {
            $keys = implode( CR.$spc, $keys);
            if( !QUOTE_NAME )
               $keys = str_replace('`', '', $keys);
            $str.= $spc.$keys.CR;
         }

         $body.= $str;
      }
      return $body;
   }
}

function dump_header( $database)
{
 global $FRIENDLY_LONG_NAME, $NOW;

   $str = "$FRIENDLY_LONG_NAME dump".chr(10);
   $str.= "Host: ".@$_SERVER['HTTP_HOST'].chr(10);
   $str.= "Database: ".$database.chr(10);
   $str.= "Generation Time: ".fdate( $NOW).chr(10);
   $str.= "Server version: ".@$_SERVER['SERVER_SOFTWARE'].chr(10);
   $str.= "PHP version: ".@phpversion().chr(10);
   $str.= "MySQL version: ".MYSQL_VERSION.chr(10);
   
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

   if( !OLD_STYLE_DUMP )
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
INSERT INTO TranslationTexts VALUES (1,'Sorry, you may not pass before all handicap stones are placed.',NULL,'Done');
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
      'TranslationTexts'
         => array('ID,Text,Ref_ID,Translatable','ID'),
      'Translations' //better to split it in different files
         => array('Original_ID,Language_ID,Text',
            (0&& OLD_STYLE_DUMP ?'' :'Language_ID,Original_ID')),
      'TranslationLanguages'
         => array('ID,Language,Name','ID'),
      'TranslationGroups'
         => array('ID,Groupname','ID'),
      'TranslationFoundInGroup'
         => array('Text_ID,Group_ID','Text_ID,Group_ID'),
      'TranslationPages'
         => array('ID,Page,Group_ID',
            (0&& OLD_STYLE_DUMP ?'' :'Page,Group_ID')),
      );

   $c=0;
   foreach( $tables as $table => $fields )
   {
      $c=($c % LIST_ROWS_MODULO)+1;
      @list($fields, $order) = $fields;
      if( $order )
         $order = ' ORDER BY '.$order;
      else
         $order = '';

      $str = insert_values( $table
            , $fields
            , "SELECT $fields FROM $table$order"
            , ''
            );

      $text.= echoTR( "Row$c", $str);
   }

   return $text;
} //transl_dump

function freesql_dump( $database, $query)
{
   $title = "Free SQL: ".$query.';';

   $result = mysql_query( $query)
            or die(mysql_error());

   $mysqlerror = @mysql_error();
   if( $mysqlerror )
   {
      echo "<p>Error: ".textarea_safe($mysqlerror)."</p>";
      return -1;
   }

   if( !$result )
      return 0;

   $numrows = @mysql_num_rows($result);
   $title .= chr(10).' => '.$numrows;
   if( $numrows<=0 )
   {
      @mysql_free_result( $result);
      return echoTR('th', comment_block( $title));
   }

   $hdrs = NULL;
   $col = 0;
   $text = '';
   $c=0;
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $c=($c % LIST_ROWS_MODULO)+1;
      $title .= ','.is_array($row).'+'.@$row['Type'];

      if( !isset($hdrs) )
      {
         $hdrs = array_keys( $row);
         $col = count($hdrs);
         $str = implode('</th><th>',$hdrs);
         $text.= "<tr><th>".$str.'</th></tr>'.CR;
      }

      $str = '';
      $sep = '';
      foreach( $hdrs as $key )
      {
         $str.= $sep.safe_value(@$row[$key]);
         $sep = '</td><td>';
      }
      $text.= "<tr class=Row$c><td>".$str.'</td></tr>'.CR;
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
      error("not_logged_in");

   $player_level = (int)$player_row['admin_level'];
   if( !($player_level & ADMIN_DATABASE) )
      error("adminlevel_too_low");

   if( $player_row['Handle'] == 'rodival' )
      $Super_admin = true;
   else
      $Super_admin = false;

   $encoding_used= get_request_arg( 'charset', LANG_DEF_CHARSET); //iso-8859-1 UTF-8

   if( $row=mysql_single_fetch( false, 'SELECT VERSION() AS version') )
   {
      define('MYSQL_VERSION', $row['version']);
/*
      $row = explode('.', MYSQL_VERSION);
      define('MYSQL_VERSION_INT', (int)sprintf('%d%02d%02d'
                  , $row[0], $row[1], intval($row[2])));
*/
   } else {
      define('MYSQL_VERSION', '3.23.32');
/*
      define('MYSQL_VERSION_INT', 32332);
*/
   }


   $dumptypes = array(
         '' => '',
         'init' => 'init.mysql',
         'transl' => 'translationdata.mysql',
      );

   $dumptype = trim(get_request_arg('dumptype'));
   if( !array_key_exists( $dumptype, $dumptypes) )
      $dumptype= '';

   $show_it= @$_REQUEST['show_it'];
   $export_it= @$_REQUEST['export_it'];

   $freesql_it= @$_REQUEST['freesql_it'];
   $freesql= trim(get_request_arg('freesql'));

   //====================

   $text = '';
   if( @$GLOBALS['Super_admin'] && $freesql_it && $freesql )
   {
      $text = freesql_dump( $DB_NAME, $freesql);
      $show_it = true;
      $dumptype = 'freeSQL';
   } else
   switch($dumptype)
   {
   case 'init': {
      $text = init_dump( $DB_NAME);
      } break; //'init'
   case 'transl': {
      $text = transl_dump( $DB_NAME);
      } break; //'transl'
   } //switch($dumptype)


   //====================

   if( $export_it && $text )
   {
      switch($dumptype)
      {
      case 'init':
         $filename= 'init.mysql';
         break;
      case 'transl':
         $filename= 'translationdata.mysql';
         break;
      default:
         $filename= 'db_export.mysql';
         break;
      }
      $filename= $FRIENDLY_SHORT_NAME.'-'.$filename;

      // this one open the text/plain in the browser by default
      //header( 'Content-type: text/plain' ); //; charset=iso-8859-1' ); //$encoding_used
      // this one exist and put a costume of binary on the text 
      //header( 'Content-type: application/octet-stream' );
      // this last does not exist but it force the "record to disk"
      header( 'Content-type: application/x-mysql' );
      header( "Content-Disposition: inline; filename=\"$filename\"" );
      header( "Content-Description: PHP Generated Data" );
   
      //to allow some mime applications to find it in the cache
      header('Expires: ' . gmdate('D, d M Y H:i:s',$NOW+5*60) . ' GMT');
      header('Last-Modified: ' . gmdate('D, d M Y H:i:s',$NOW) . ' GMT');

      echo $text;
      exit;
   } //$export_it


   //====================

   start_html( 'data_export', 0, '', //@$player_row['SkinName'],
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   echo " <SCRIPT language=\"JavaScript\" type=\"text/javascript\"><!-- \n";
   echo "   function row_click(row,rcl) {
     row.className=((row.className=='hil')?rcl:'hil');
   }\n";
   echo "\n//-->\n</SCRIPT>\n";


   $dform = new Form('dform', 'data_export.php#result', FORM_POST, true );

   //$dform->add_hidden( $key, $val)
   $dform->add_row( array(
      'DESCRIPTION', 'Dump type',
      'SELECTBOX', 'dumptype', 1, $dumptypes, $dumptype, false,
      ) );
   $dform->add_row( array(
      'HIDDEN', 'new_style', (OLD_STYLE_DUMP ?'0' :'1'),
      'SUBMITBUTTONX', 'show_it', 'Show it [&s]',
               array('accesskey' => 's'),
      'SUBMITBUTTONX', 'export_it', 'Download it [&d]',
               array('accesskey' => 'd'),
      ) );

   if( @$GLOBALS['Super_admin'] )
   {
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
         , '', array( 'accesskey' => 'd' ) ) . "<br>";
   }

   end_html();
}

?>