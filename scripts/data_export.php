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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
//require_once( "include/form_functions.php" );


define('QUOTE_NAME', 1);
define('CREATE_TIME', 1);
define('DROP_TABLE', 0);
define('IF_NOT_EXISTS', 0);
define('AUTO_INCREMENT', 0);


//define('COMMENT_LINE_STR', '//');
//define('COMMENT_LINE_STR', '#');
define('COMMENT_LINE_STR', '--');

define('CR', chr(13).chr(10));
//define('CR', chr(13));
//define('CR', chr(10));
//define('CR', '<br>');

//define('QUOTE', '`'); //backquote
define('QUOTE', "'");


function get_tables( $database)
{
   if(1){
     $tables = array (
         'Bio',
         'Clock',
         'Errorlog',
         'FAQ',
         'FAQlog',
         'Folders',
         'Forumreads',
         'Forums',
         'Games',
         'Games2', //not local
         'GoDiagrams',
         'MessageCorrespondents',
         'Messages',
         'MoveMessages',
         'Moves',
         'Observers',
         'Players',
         'Posts',
         'RatingChange',
         'Ratinglog',
         'Statistics',
         'TranslationFoundInGroup',
         'TranslationGroups',
         'TranslationLanguages',
         'TranslationPages',
         'TranslationTexts',
         'Translationlog',
         'Translations',
         'Waitingroom',


   //DGS but not local:      
         'dragondisc',
         'dragondisc_bodies',
         'faq', //take care: doublon of the uppercase one
         'faq_bodies',
         'forums', //take care: doublon of the uppercase one
         'godisc',
         'godisc_bodies',
         'news',
         'news_bodies',
         'opponents',
         'opponents_bodies',
         'support',
         'support_bodies',
         'transl',
         'transl_bodies',


   //local but not DGS:      
         //new ones to add
         'Adminlog',

         //old ones disappeared
         'tournament',
         'tournamentorganizers',
         'tournamentparticipants',
         'tournamentround',
         'knockout', //tournament

      );
   } else { //1/0
      $result = mysql_query( 'SHOW TABLES FROM ' . quoteit( $database) );

      $mysqlerror = @mysql_error();
      if( $mysqlerror )
      {
         echo "Error: $mysqlerror<p>";
         return -1;
      }

      $tables = array();
      while( list($row) = mysql_fetch_row( $result ) )
      {
         $tables[] = $row;
      }
      mysql_free_result($result);
      sort( $tables);
   }

   return $tables;
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
   if( !empty($mixed) or is_numeric($mixed) )
      return $quote . trim( $mixed, " '`$quote") . $quote;
   return $mixed;
}

function adj_eol( $str, $cr=CR, $trim=' ')
{
   //if( $cr===false ) $cr = CR;
   return ereg_replace(
         "[$trim\x01-\x1f]*[\x0a\x0d]+[$trim\x01-\x1f]*", 
         $cr, 
         $str );
}

function fdate( $sdat)
{
   $fmt= 'Y-m-d H:i:s \G\M\T'; //O e
   
   if( is_string( $sdat) )
      $sdat = strtotime($sdat);
   return date( $fmt, $sdat); //date gmdate
}

function dump2html( $str)
{
   return str_replace( ' ', '&nbsp;' //&nbsp;&deg;
         , str_replace( CR, "<br>\n", $str)) //§
         ;
}


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
   var $type;

   function dbTable( $database, $name)
   {
      $this->qdatabase = quoteit( $database);
      $this->qname = quoteit( $name);
      $this->qpath = $this->qdatabase . '.' . $this->qname;
      $this->uname = substr( $this->qname, 1, -1);

      $this->xname = quoteit( $name, ( QUOTE_NAME ? QUOTE :'') );

      $this->type = '';
   }

   function structure()
   {
      $comment = '';
      $head = '';
      $body = '';
      $incr = '';
      $ok = 0;

      if( $row=mysql_single_fetch( 
            'SHOW TABLE STATUS FROM ' . $this->qdatabase
            . ' LIKE \'' . $this->uname . '\'') )
      {

         if( @$row['Type'] )
         {
            $ok = 1;
            $this->type = $row['Type'];
            if( AUTO_INCREMENT && @$row['Auto_increment'] )
               $incr = ' AUTO_INCREMENT=' . $row['Auto_increment'] . ' ';

            if( CREATE_TIME && @$row['Create_time'] ) //also 'Update_time'
               $comment .= 'Created: '.fdate( $row['Create_time']).chr(10);
         }
      }

      if( !$ok )
         $comment.= 'Not found.'.chr(10);

      $struct = CR.
           comment_block( 'Table structure for table '.$this->qname
               .chr(10).$comment);

      if( $ok )
      {
         if( DROP_TABLE )
            $head.= 'DROP TABLE IF EXISTS '.$this->qname.';'.CR;

         mysql_query('SET SQL_QUOTE_SHOW_CREATE='
            . ( QUOTE_NAME ?'1' :'0') ) or die(mysql_error());

         if( !($body = $this->structure_body()) )
            $body = 'Error: body';

         $struct.= CR
            . $head
            . 'CREATE TABLE '
               . ( IF_NOT_EXISTS ?'IF NOT EXISTS ' :'' )
               . $this->xname.' ('.CR
            . $body
            . ') TYPE='.$this->type.$incr.';'.CR
            ;
      }
      return $struct;
   }

   function structure_body()
   {
      $body = '';
      if( $row=mysql_single_fetch(
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

         $row = '   ';
         $str = $row . adj_eol( $str, CR.$row) . CR;

         $body.= $str;
      }
      return $body;
   }
}


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


   $encoding_used= get_request_arg( 'charset', LANG_DEF_CHARSET); //iso-8859-1 UTF-8

   if( $row=mysql_single_fetch( 'SELECT VERSION() AS version') )
   {
      define('MYSQL_VERSION', $row['version']);
/*
      $row = explode('.', MYSQL_VERSION);
      define('MYSQL_VERSION_INT', (int)sprintf('%d%02d%02d'
                  , $row[0], $row[1], intval($row[2])));
*/
   } else{
      define('MYSQL_VERSION', '3.23.32');
/*
      define('MYSQL_VERSION_INT', 32332);
*/
   }

/*
   if( isset($_REQUEST['rowhdr']) )
      $rowhdr= $_REQUEST['rowhdr'];
   else
      $rowhdr= 20;

   $apply= @$_REQUEST['apply'];
*/

   start_html( 'data_export', 0, 
      "  table.tbl { border:0; background: #c0c0c0; }\n" .
      "  tr.row1 { background: #ffffff; }\n" .
      "  tr.row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

   echo " <SCRIPT language=\"JavaScript\" type=\"text/javascript\"><!-- \n";
   echo "   function row_click(row,rcl) {
     row.className=((row.className=='hil')?rcl:'hil');
   }\n";
   echo "\n//-->\n</SCRIPT>\n";


   //$dform = new Form('dform', 'data_export.php#result', FORM_POST, true );

   $tables = get_tables( $DB_NAME);
   if(  is_array( $tables) )
   {
      $database = $DB_NAME;

      echo "\n<table class=tbl cellpadding=4 cellspacing=1>\n";

      echo "<tr>\n";
         $str = "DragonGo dump".chr(10);
         $str.= "Host: ".@$_SERVER['HTTP_HOST'].chr(10);
         $str.= "Database: ".$database.chr(10);
         $str.= "Generation Time: ".fdate( $NOW).chr(10);
         $str.= "Server version: ".@$_SERVER['SERVER_SOFTWARE'].chr(10);
         $str.= "PHP version: ".@phpversion().chr(10);
         $str.= "MySQL version: ".MYSQL_VERSION.chr(10);

         $str = comment_block( $str);
         echo '<th nowrap align=left>' . dump2html( $str) . "</th>\n";
      echo "</tr>\n";

      $c=2;
      $i=0;
      foreach( $tables as $table)
      {
         $c=3-$c;
         $i++;
         echo "<tr class=row$c ondblclick=\"row_click(this,'row$c')\">\n";

         $tbl = new dbTable( $database, $table);
         $str = $tbl->structure();
         echo '<td nowrap>' . dump2html( $str) . "</td>\n";

         echo "</tr>\n";
      }
      echo "</table>\n";
   }

   end_html();
}

?>
