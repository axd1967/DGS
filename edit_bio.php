<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/table_infos.php';
require_once 'include/rating.php';

define('USER_BIO_ADDENTRIES', 3);


function find_category_box_text($cat)
{
   global $categories;
   return ( array_key_exists($cat, $categories) ) ? $cat : '';
}


{
   // NOTE: using page: change_bio.php

/* Actual REQUEST calls used:
     ri=1         : add/pre-fill new bio-entry with rank-info (of go-servers)
*/

   connect2mysql();

   $logged_in = who_is_logged( $player_row );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'edit_bio');
   if ( (@$player_row['AdminOptions'] & ADMOPT_DENY_EDIT_BIO) )
      error('edit_bio_denied', 'edit_bio');

   $my_id = $player_row['ID'];
   $editorder = isset($_REQUEST['editorder']);

   $result = db_query( "edit_bio.find_bios($my_id)",
      "SELECT * FROM Bio WHERE uid=$my_id ORDER BY SortOrder" );
   $row_cnt = @mysql_num_rows($result);

   $cat_rank_info = 'Rank info';
   $categories = array(
         '' => T_('Other:'),
         'Country' => T_('Country'),
         'City' => T_('City'),
         'State' => T_('State'),
         'Club' => T_('Club'),
         'Homepage' => T_('Homepage'),
         'Email' => T_('Email'),
         'ICQ-number' => T_('ICQ-number'),
         'Game preferences' => T_('Game preferences'),
         'Hobbies' => T_('Hobbies'),
         'Occupation' => T_('Occupation'),
         'Native Language' => T_('Native Language'),
         'Language Competence' => T_('Language Competence'),
         $cat_rank_info => T_('Rank info'),
      );


   $page = "edit_bio.php";
   $title = T_("Edit biographical info");
   $othertitle = T_("Edit biographical order");
   if ( $editorder ) {
      $str = $title;
      $title = $othertitle;
      $othertitle = $str;
   }

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";


   $cat_max = 40;
   $cat_width = 20;
   $text_width = 70;
   $text_height = 4;

   $moveurl = 'change_bio.php';
   if ( !$editorder )
      $bio_form = new Form( 'bioform', $moveurl, FORM_POST );
   else
      $bio_table = new Table_info('biomove');
   $moveurl .= '?';

   $arr_categories = array();
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $bid = $row['ID'];
      $other = $row['Category'];
      $cat = find_category_box_text($other);
      if ( $cat )
      {
         $other = '';
         $arr_categories[$cat] = $bid;
      }
      else
      {
         if ( substr( $other, 0, 1) == '=' )
            $other = substr( $other, 1);
         $other = make_html_safe($other, INFO_HTML);
      }

      if ( !$editorder )
      { //edit bio fields
         // adapt text-height
         $linecount = substr_count( $row['Text'], "\n" );
         $txth_adapted = max( $text_height, min( 12, (int)($linecount / 1.3) ));

         $bio_row = array(
            'CELL', 0, 'class=Header',
            'SELECTBOX', "category$bid", 1, $categories, $cat, false,
            'BR',
            'TEXTINPUT', "other$bid", $cat_width, $cat_max, $other,
            'CELL', 0, 'class=Info',
            'TEXTAREA', "text$bid", $text_width, $txth_adapted, $row['Text'],
         );

         $bio_form->add_row( $bio_row );
      }
      else
      { //edit fields order
         //$text = make_html_safe($row['Text'], false);
         $text = textarea_safe($row['Text']);
         $text = "<TEXTAREA readonly name=\"dtext$bid" //readonly disabled
            . "\" cols=\"$text_width\" rows=\"$text_height\">$text</TEXTAREA>";

         $bio_table->add_sinfo(
            '<div>'
               . ($cat ?$categories[$cat] :($other ?$other :'&nbsp;'))
               . "</div><div class=center>"
               . anchor($moveurl."move$bid=1",
                     image( 'images/down.png', 'down'), T_("Move down"), null)
               . anchor($moveurl."move$bid=-1",
                     image( 'images/up.png', 'up'), T_("Move up"), null)
               . "</div>",
            //don't use add_info() to avoid the INFO_HTML here:
            $text
         );
      }
   }//while ($row)
   mysql_free_result($result);

   if ( !$editorder )
   {
      // fill-in default-template for entering rank-info (of other Go-servers)
      $add_rank_info = '';
      if ( @$_REQUEST['ri'] && !isset($arr_categories[$cat_rank_info]) )
      {
         $dgs_rank = echo_rating( $player_row['Rating2'], false, 0, true, 1);
         $add_rank_info = sprintf('DGS %s, KGS , IGS , OGS , Tygem , Wbaduk , EGF , AGA , Japan , China , Korea , FICGS , IYT , LittleGolem, INGO',
            ($dgs_rank ? $dgs_rank : '?') );
      }

      // And now some empty ones:
      for ($i=1; $i <= USER_BIO_ADDENTRIES; $i++)
      {
         $bio_form->add_row( array(
               'CELL', 0, 'class=NewHeader',
               'SELECTBOX', "newcategory" . $i, 1, $categories, ($add_rank_info ? $cat_rank_info : ''), false,

               'BR',
               'TEXTINPUT', "newother" . $i, $cat_width, $cat_max, "",

               'CELL', 0, 'class=NewInfo',
               'TEXTAREA', "newtext" . $i, $text_width, $text_height, ($add_rank_info ? $add_rank_info : ''),
            ));
         $add_rank_info = 0;
      }

      $bio_form->add_row( array(
            'HIDDEN', 'newcnt', USER_BIO_ADDENTRIES,
            'SUBMITBUTTONX', 'action', T_('Change bio'), array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
         ));
      $bio_form->echo_string(1);
   }
   else
   {
      echo '<p>'.T_('change order only, no text-change possible here.').'</p>';
      $bio_table->echo_table();
   }


   $menu_array = array();
   if ( !$editorder )
      $page = ( $row_cnt > 1 ) ? make_url($page, array( 'editorder' => '1') ) : '';
   if ( $page )
      $menu_array[$othertitle] = $page;

   end_page(@$menu_array);
}
?>
