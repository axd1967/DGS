<?php
/* Author: Rod Ival */

/* NOTE: only runs in Rods environment, has some unresolved dependencies. */


//Analyze the DGS slow.log file

error_reporting(E_ALL);

//Warning: Filename must be 8.3
$BASENAME=explode('.',$argv[0]); $BASENAME=$BASENAME[0];
$BASENAME=strtolower(str_replace('\\','/',$BASENAME));
if(1){
  $BASEDIR= $BASENAME;
  $BASENAME= strrchr($BASEDIR,'/');
  //if( !$BASENAME ) $BASENAME= strrchr($BASEDIR,'\\');
  if( !$BASENAME ) $BASENAME= '/'.$BASEDIR;
//echo CR.$BASENAME;
  $BASENAME= $BASEDIR.$BASENAME;
}else{
  $BASEDIR='.';
}
//echo CR.$BASENAME.' '.$BASEDIR; exit;

define('SNAP_OUT',1); //copy the screen output in $BASENAME.'.lst'

if(0) {
$CONTEXT_FILE= $BASENAME.'.ini';
//@copy( $CONTEXT_FILE, tempnam($BASEDIR,'tmp') );
//noms de variables GLOBALES (sans $) (array=>no define())
//init: $CONTEXT_VARS= array('forum_date','forum_time','verif_num');
//init: foreach($CONTEXT_VARS as $key) { $$key=''; }
//default $CONTEXT_VARS:
//$games= array();
$Logfp= fopen($BASENAME.'.rpt', 'w');
require_once('curl.php'); // J:\common\curl.php
require_once('Common.php'); // J:\common\Common.php
require_once('Dragon.php'); // J:\common\Dragon.php
} else {
define('CR',"\r\n"); //for output to screen
define('MY_GETBUFSIZ', 32000);
}

  $NOW= time();

if (SNAP_OUT) {
   //$stdout = fopen('php://stdout', 'w');
   $Scrfp= fopen($BASENAME.'.tmp', 'w');
   function ob_echo($str) {
    global $Scrfp;
      fputs($Scrfp, $str);
      return $str;
   }
   function ob_flushx() {
      ob_flush(); //ne marche qu'une fois avec certain php-cli ??
      //ob_end_flush(); ob_start("ob_echo"); //ceci est la solution, alors.
   }
   @ob_clean(); ob_start("ob_echo");
   //ob_implicit_flush(1); //ne suffit pas (au mieux 1 flush final) même si activées dans php.ini
}else{
   function ob_flushx() { }
} //SNAP_OUT

function Mydate($tim) { return date('Y-m-d H:i',$tim); }

//error
$E1n=$E1s=$E2n=$E2s=$E3n=$E3s='';
function Myerror($s)
{
   global $E1n,$E1s,$E2n,$E2s,$E3n,$E3s;
   ob_flushx();
   //echo CR."#Err: $E1n,$E1s,$E2n,$E2s,$E3n,$E3s,$s";
   echo CR."#Err: $s";
   ob_flushx();
   exit;
}

//=============================================================================
$str= "* Parse a MySQL slow.log file *";
echo/*title*/($str);
ob_flushx();


function Myarg( $help, &$i, $key, $arg)
{
   global $argc, $argv;
   $str= $key;
   $var=& $arg[0];
   $desc= $arg[1];
   $args= $arg[2];
   $val= array();
   $full= 1;
   if( !is_array( $var) )
      $tmp= array($var);
   else
      $tmp= $var;
   reset( $tmp);
   //if( !$help ) echo CR."1.i=$i f=$full t=".var_export($tmp,true);
   foreach( $args as $aname => $aunit )
   {
      $str.= " <$aname>";
      if( $s=each($tmp) )
         $str.= ' ['.$s['value'].']';
         //$str.= '['.var_export($s['value'],true).']';
      if( $aunit )
         $str.= " ($aunit)";
      if( $help || !$full )
         continue;
      if( ++$i < $argc )
      {
         $s= (string)$argv[$i];
         if( substr($s,0,2)=='--' )
            $val[]= substr($s,1);
         else if( $s[0]!='-' )
            $val[]= $s;
         else
            $full= 0;
      }
      else
         $full= 0;
   }
   //if( !$help ) echo CR."2.i=$i f=$full v=".var_export($val,true);
   if( $help || !$full )
   {
      if( !$help )
         $desc= 'missing argument';
      echo CR.' '.$str.': '.$desc;
      if( $help )
         return 0; //continue;
      // !$full
      $i--;
      return 1; //break;
   }
   // !$help && $full
   switch( count($args) )
   {
      case 0: $var= !$var; break;
      case 1: $var= $val[0]; break;
      default: $var= $val; break;
   }
   //echo CR."$key=".var_export($var,true);
   return 0; //continue;
}

   $verbose= 0;
   $sleep= 0;
   $max_duration= 300;
   $query_len= 80;
   $skip_missing_date= 0;
   $min_date= '2008-00-00';
   $max_date= '2999-00-00';
   $outtype= 'mysql';
   $infile= '/tmp/slow.log';
   $outfile= $BASENAME.'.mysql';

   $args= array(
      '-h' =>
         array( &$help, 'this list'
         , array() ),
      '-v' =>
         array( &$verbose, 'more messages displayed (verbose)'
         , array() ),
      '-s' =>
         array( &$sleep, 'pause before start'
         , array('sec' => 'seconds') ),
      '-max_duration' =>
         array( &$max_duration, 'limit of the Query_time kept'
         , array('sec' => 'seconds') ),
      '-query_len' =>
         array( &$query_len, 'length of the query\'s chunk recorded'
         , array('len' => '') ),
      '-skip_missing_date' =>
         array( &$skip_missing_date, 'skip the log entry if the date is missing'
         , array() ),
      '-min_date' =>
         array( &$min_date, 'lower bound of the date interval'
         , array('date' => 'YYYY-MM-DD') ),
      '-max_date' =>
         array( &$max_date, 'upper bound of the date interval'
         , array('date' => 'YYYY-MM-DD') ),
      '-i' =>
         array( &$infile, 'name of the slow_log file'
         , array('infile' => '') ),
      '-o' =>
         array( &$outfile, 'name of the output file'
         , array('outfile' => '') ),
      '-type' =>
         array( &$outtype, 'format of the output file'
         , array('typ' => 'mysql|csv') ),
      );

   $help= 0;
   //echo CR."n=$argc";
   for($i=1 ; $i<$argc ; $i++)
   {
      //echo CR."i=$i ".$argv[$i];
      if( $arg=@$args[$k=$argv[$i]] )
      {
         if( !Myarg( $help, $i, $k, $arg) )
            continue;
      }
      else
         echo CR." $k: switch unknown";
      $help= 1;
      break;
   }
   if( $help )
   {
      echo CR.'Usage: [-h] [-s <sec>] [-v] [-max_duration <sec>]'
         .' [-query_len <len>]'
         .CR.'  [-skip_missing_date] [-min_date <date>] [-max_date <date>]'
         .CR.'  [-i <infile>] [-o <outfile>] [-type <typ>]';
      foreach( $args as $k => $arg )
         if( Myarg( $help, $i, $k, $arg) ) break;
      exit;
   }
   unset($args);
   define('VERBOSE', $verbose);

   if( $sleep > 0 )
   {
      echo CR."Pause for $sleep secondes... ";
      ob_flushx();
      sleep($sleep);
      echo "ok.";
      ob_flushx();
   }


   //slow log - switches:
   define('SLOW_FILE', $infile);
   define('RES_TYPE', (string)$outtype);
   define('SKIP_MISSING_DATE', $skip_missing_date); //skip the log entry if a clear time is missing
   define('MAX_DURATION', max(4,$max_duration)); //(seconds) upper bound of the Query_time kept
   define('QUERY_LEN', max(0,$query_len)); //part kept from the guilty query
   define('MIN_DATE', $min_date); //lower bound of the date interval
   define('MAX_DATE', $max_date); //upper bound of the date interval

   define('GROUP_BY', (string)0); //Y|M|D|H or 0

   //slow log - IO files:
   switch( (string)RES_TYPE )
   {
   case 'csv':
   case 'mysql':
      define('RES_FILE', $outfile);
      break;
/*
   case 'database':
      if( !$dbcnx) {
         Myerror("localMySQL (EasyPHP?) absent. Can't continue. Open it and retry.");
      }
      if( !@mysql_select_db($DB_NAME) )
      {
         @mysql_close( $dbcnx);
         $dbcnx= 0;
         Myerror('mysql_select_db_failed');
      }
*/
   default:
      define('RES_FILE', '');
      break;
   }

if( isset($dbcnx) && $dbcnx )
{
function Myslashes($s) { return mysql_real_escape_string($s); }
}
else
{
//function Myslashes($s) { return addslashes($s); }
function Myslashes($s) { return mysql_escape_string($s); }
}

if (!function_exists('fputcsv')) {
   function fputcsv($h, $row, $separator=',', $enclosure='"')
   {
      foreach ($row as $idx => $cell)
         if( !is_numeric($cell) )
            $row[$idx] = $enclosure
               .str_replace($enclosure, $enclosure.$enclosure, $cell)
               .$enclosure;
      return fputs($h, implode($row, $separator));
   }
}


   $starttime= time();
//=========================

   $e= error_reporting(E_ALL & ~E_WARNING);
   $slowfp= fopen(SLOW_FILE, 'r');
   if(!$slowfp)
      Myerror("File not found: ".SLOW_FILE);
   echo CR."Read: ".SLOW_FILE;

   if(RES_FILE)
   {
      $resfp= fopen(RES_FILE, 'w');
      if(!$resfp)
         Myerror("File write error: ".RES_FILE);
      echo CR."Write: ".RES_FILE;
   }
   else
      $resfp= false;
   error_reporting($e);

   switch( (string)RES_TYPE )
   {
   case 'csv':
      fputcsv($resfp, array(
         "Date","Query_time","Lock_time","Rows_sent","Rows_examined","Query"
         ) );
      break;
   case 'mysql':
      //DROP TABLE SlowLog
      $str= "
CREATE TABLE IF NOT EXISTS SlowLog (
   ID int(11) NOT NULL auto_increment,
   Grp int(11) NOT NULL default '0',
   Date datetime NOT NULL default '0000-00-00 00:00:00',
   Query_time int(11) NOT NULL default '0',
   Lock_time int(11) NOT NULL default '0',
   Rows_sent int(11) NOT NULL default '0',
   Rows_examined int(11) NOT NULL default '0',
   Query varchar(".QUERY_LEN.") NOT NULL default '',
   PRIMARY KEY (ID),
   KEY Date (Date)
) TYPE=MyISAM;
";
      fputs($resfp, $str);

/**** useful queries:
UPDATE SlowLog SET Grp=HOUR(Date);
UPDATE SlowLog SET Grp=DAYOFWEEK(Date);
UPDATE SlowLog SET Grp=DAYOFYEAR(Date);
SELECT Grp, Cnt, Qtime, Qtime/Cnt as Avg FROM
(SELECT COUNT(*) as Cnt, SUM(Query_time) as Qtime, Grp FROM SlowLog
GROUP BY Grp) AS T ORDER BY Grp

SELECT mDate, Grp, Cnt, Qtime, Qtime/Cnt as Avg FROM
(SELECT COUNT(*) as Cnt, SUM(Query_time) as Qtime, Grp
 , MIN(Date) AS mDate FROM SlowLog
GROUP BY Grp) AS T ORDER BY Grp

//which queries are more painful?
SELECT Grp, Cnt, Qtime, Qtime/Cnt as Avg, MDate FROM
(SELECT COUNT(*) as Cnt, SUM(Query_time) as Qtime, Query AS Grp
, MAX(Date) as MDate FROM SlowLog
WHERE Date>'2008-04-10' GROUP BY Grp) AS T ORDER BY Qtime desc

SELECT * FROM SlowLog WHERE Date>'2008-04-09' ORDER BY Rows_sent desc
 Query_time  Lock_time  Rows_sent  Rows_examined  Query
 7           0          14832      340785         SELECT GAMES.*, GAMES.ID AS GID, CLOCK.TICKS AS TICKS FROM GAMES, CLOCK WHERE CL

UPDATE SlowLog SET Grp=FLOOR(Query_time/10);
SELECT Grp, Cnt, Qtime, Qtime/Cnt as Avg, Qtime/Cnt/10-Grp-.5 as Val FROM
(SELECT COUNT(*) as Cnt, SUM(Query_time) as Qtime, Grp FROM SlowLog
GROUP BY Grp) AS T ORDER BY Grp

*****/

      $CHUNK_SIZE= 500;
      $CHUNK_OPEN= "\nINSERT INTO SlowLog
 (Date,Query_time,Lock_time,Rows_sent,Rows_examined,Query) VALUES";
      $CHUNK_CLOSE= "\n;\n";
      break;
   } //switch( RES_TYPE )

   echo CR; //-----------
/*
# Time: 080505 22:45:52
# User@Host: gowww[gowww] @ localhost []
# Query_time: 14  Lock_time: 0  Rows_sent: 21  Rows_examined: 341378
SELECT... (QUERY_ZON)
# ...
*/
   define('ENTRY_HDR', '#');
   define('ENTRY_HDR_LEN', strlen(ENTRY_HDR));
   define('QUERY_ZON', 9);
   $rescnt= 0;
   $chunk_start= 1; $chunk_closed= 1;
   $zon= 0;
   $query= $qdat= ''; $qrow= array();
   for(;;)
   { //foreach(input lines)
      if(!feof($slowfp))
      {
         $str = trim(fgets($slowfp, MY_GETBUFSIZ));
         if(!$str) continue;
      }
      else if( $zon == QUERY_ZON )
      {
         $str = ENTRY_HDR; //add a fake stop line
      }
      else
         break;

      //if($rescnt<1) echo CR.$rescnt.': '.$str;
      if( !strcasecmp(ENTRY_HDR, substr($str,0,ENTRY_HDR_LEN)) )
      {
         if( strcasecmp(ENTRY_HDR.' admin', substr($str,0, 6+ENTRY_HDR_LEN))
            &&  $zon == QUERY_ZON && $qdat>'' )
         { //end of the variable QUERY_ZON => end of the entry
            if( count($qrow)<4 ) Myerror('qrow_too_small');
            $rescnt++;
            //if( $rescnt > 20 ) break;
            if( VERBOSE )
            {
               echo CR.sprintf('%06d> %s',(int)$rescnt, $qdat)
                  .vsprintf(' qt=%s lt=%s rs=%s re=%s', $qrow);
               echo CR.substr($query,0,75);
            }
            else
               echo "\r$rescnt ";
            ob_flushx();
            $query= trim(preg_replace( "%[\\x1-\\x20\\x80-\\xff]+%", ' ', $query));
            $query= substr($query,0,QUERY_LEN);
            $query= strtoupper( $query);
            switch( (string)RES_TYPE )
            {
            case 'csv':
               //Date,Query_time,Lock_time,Lock_time,Rows_sent,Rows_examined,Query
               array_unshift($qrow, $qdat);
               $qrow[]= $query;
               fputcsv($resfp, $qrow);
               break;
            case 'mysql':
               if( $chunk_closed && ($rescnt % $CHUNK_SIZE) == 1 )
               {
                  fputs($resfp, $CHUNK_OPEN);
                  $chunk_start= 1;
               }
               //(Date,Query_time,Lock_time,Lock_time,Rows_sent,Rows_examined,Query) VALUES
               $tmp = sprintf("\n%s('%s'", ($chunk_start?' ':','), $qdat);
               $tmp.= vsprintf(",%s,%s,%s,%s", $qrow);
               $tmp.= sprintf(",'%s')", Myslashes($query));
               fputs($resfp, $tmp);
               $chunk_closed= 0;
               if( !$chunk_closed && ($rescnt % $CHUNK_SIZE) == 0 )
               {
                  fputs($resfp, $CHUNK_CLOSE);
                  $chunk_closed= 1;
               }
               break;
            } //switch( RES_TYPE )
            $chunk_start= 0;
            $qrow= array();
            if( SKIP_MISSING_DATE ) $qdat= '';
            $zon= 0;
         }
         $tmp= trim(substr($str, ENTRY_HDR_LEN));
         foreach( array(
            'Time:' => 1,
            'User@Host:' => 2,
            'Query_time:' => 3,
            'administrator' => 4,
            //Query_zone => QUERY_ZON
            ) as $key => $val) {
            if( !strcasecmp( $key, substr($tmp,0, $k=strlen($key))) )
            {
               $zon= $val;
               $str= trim(substr($tmp, $k));
               break;
            }
         }
      } //ENTRY_HDR header
      switch($zon)
      {
      case 1:
         if( preg_match(
            '%^(\d\d)(\d\d)(\d\d)\s+(\d+):(\d+):(\d+)%i'
            , $str, $m ))
         {
            array_shift($m);
            switch( (string)GROUP_BY )
            {
            case 'Y': $m[1]=1;
            case 'M': $m[2]=1;
            case 'D': $m[3]=0;
            case 'H': $m[4]=0; $m[5]=0;
            default: break;
            }
            $str= vsprintf("20%02d-%02d-%02d %02d:%02d:%02d",$m);
            if( $str >= MIN_DATE && $str < MAX_DATE )
            {
               $qdat= $str;
               break;
            }
            $qdat= '';
         }
         $zon= 0;
         break;
      case 2:
         break;
      case 3:
         $query= '';
         if( preg_match(
            '%^(\d+)\s*Lock_time:\s*(\d+)\s*Rows_sent:\s*(\d+)\s*Rows_examined:\s*(\d+)%i'
            , $str, $m ))
         {
            if( $m[1] < MAX_DURATION )
            {
               array_shift($m);
               //$m[0]= 0; //cnt
               $qrow= $m;
               $zon= QUERY_ZON;
               break;
            }
         }
         $zon= 0;
         break;
      case 4:
         $zon= QUERY_ZON;
         //break;
      case QUERY_ZON:
         $query= trim($query.' '.$str);
         break;
      default:
         $zon= 0;
         break;
      } //switch($zon)
      //echo '*'.$zon;
      //if( $qdat == '2008-01-02 23:36:02' ) break;
   } //foreach(input lines)

   switch( (string)RES_TYPE )
   {
   case 'csv':
      break;
   case 'mysql':
      //echo CR."$rescnt>0 && !$chunk_closed && !$chunk_start";
      if( !$chunk_closed && !$chunk_start )
      {
         fputs($resfp, $CHUNK_CLOSE);
         $chunk_closed= 1;
      }
      $str= "\nUPDATE SlowLog SET Grp=DAYOFWEEK(Date);\n";
      fputs($resfp, $str);
      break;
   } //switch( RES_TYPE )
   fclose($resfp);
   fclose($slowfp);

//=========================
//end
   $starttime= time()-$starttime;
   echo CR."Ok ".Mydate($NOW)." - {$starttime}s -";

if (SNAP_OUT) {
   $src=$BASENAME.'.tmp'; //see above $Scrfp filename
   $dst=$BASENAME.'.lst';
   ob_end_flush(); fclose($Scrfp);
   if( file_exists($dst) ) unlink($dst);
   rename($src,$dst);
} //SNAP_OUT

exit;
?>
