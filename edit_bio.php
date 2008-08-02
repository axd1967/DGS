<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( 'include/table_infos.php' );

define('USER_BIO_ADDENTRIES', 3);


function find_category_box_text($cat)
{
   global $categories;

   if( array_key_exists($cat, $categories) )
      return $cat;
   else
      return '';
}

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = $player_row['ID'];
   $editorder = isset($_REQUEST['editorder']);

   $result = mysql_query("SELECT * FROM Bio where uid=$my_id"
               . " order by SortOrder, ID")
      or error('mysql_query_failed', 'edit_bio.find_bios');
   $row_cnt = @mysql_num_rows($result);

   $categories = array( '' => T_('Other:'),
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

   {
      $cat_max = 40;
      $cat_width = 20;
      $text_width = 60;
      $text_height = 4;

      $moveurl = 'change_bio.php';
      if ( !$editorder )
      {
         $bio_form = new Form( 'bioform', $moveurl, FORM_POST );
      }
      else
      {
         $bio_table= new Table_info('biomove');
      }
      $moveurl.= '?';

      while( $row = mysql_fetch_assoc( $result ) )
      {
         $bid = $row['ID'];
         $other = $row['Category'];
         $cat = find_category_box_text($other);
         if( $cat )
            $other = '';
         else
         {
            if( substr( $other, 0, 1) == '=' )
               $other = substr( $other, 1);
            $other = make_html_safe($other, INFO_HTML);
         }

         if ( !$editorder )
         { //edit bio fields
            $bio_row = array(
               'CELL', 0, 'class=Header',
               'SELECTBOX', "category$bid", 1, $categories, $cat, false,
               'BR',
               'TEXTINPUT', "other$bid", $cat_width, $cat_max, $other,
               'CELL', 0, 'class=Info',
               'TEXTAREA', "text$bid", $text_width, $text_height, $row['Text']
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
                     . anchor($moveurl."move$bid=1"
                           , image( 'images/down.png', 'down')
                           , T_("Move down"))
                     . anchor($moveurl."move$bid=-1"
                           , image( 'images/up.png', 'up')
                           , T_("Move up"))
                     . "</div>"
               //don't use add_info() to avoid the INFO_HTML here:
               ,$text
            );
         }
      } //while($row)
      mysql_free_result($result);

      if ( !$editorder )
      {
         // And now some empty ones:
         for($i=1; $i <= USER_BIO_ADDENTRIES; $i++)
         {
            $bio_form->add_row( array(
                  'CELL', 0, 'class=NewHeader',
                  'SELECTBOX', "newcategory" . $i, 1, $categories, '', false,

                  'BR',
                  'TEXTINPUT', "newother" . $i, $cat_width, $cat_max, "",

                  'CELL', 0, 'class=NewInfo',
                  'TEXTAREA', "newtext" . $i, $text_width, $text_height, "",
               ) );
         }

         $bio_form->add_row( array(
                  'HIDDEN', 'newcnt', USER_BIO_ADDENTRIES,
                  'SUBMITBUTTONX', 'action', T_('Change bio'),
                     array( 'accesskey' => ACCKEY_ACT_EXECUTE )
               ) );
         $bio_form->echo_string(1);
      }
      else
      {
         echo '<p>'.T_('change order only, no text-change possible here.').'</p>';
         $bio_table->echo_table();
      }
   }

   $menu_array[T_('Show/edit userinfo')] = "userinfo.php?uid=$my_id";

   if ( !$editorder )
   {
      if ( $row_cnt > 1 )
         $page = make_url($page, array( 'editorder' => '1') );
      else
         $page = '';
   }
   if ( $page )
      $menu_array[$othertitle] = $page;

   end_page(@$menu_array);
}
?>
