<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/make_translationfiles.php" );

define('ALLOW_PROFIL_CHARSET', 0);
define('TRANSL_ALLOW_FILTER', 1);


$info_box = '<table border="2">
<tr><td>
&nbsp;When translating you should keep in mind the following things:
<ul>
  <li> If a translated word is the same as in english, leave it blank and click
       the \'untranslated\' box to the right.
  <li> In some places there is a percent-character followed by some characters.
       This is a special place where the program might put some data in.
       <br>
       Example: \'with %s extra per move\' might be displayed as \'with 2 hours extra per move\'.
       <br>
       If you want to change order of these you can use \'%1$s\' to place to make
       sure that you get the first argument and \'%2$s\' for the second etc.
       <br>
       <a href="http://www.php.net/manual/en/function.sprintf.php">You can read more here</a>
  <li> In some strings there are html code. If you don\'t know how to use html code,
       just copy the original code and change the real language. If you are unsure
       you can use the translator forum to get help.
  <li> If you want to change the html code in some way in the translation, keep in mind
       that the code shall conform to the standard layout of Dragon.
  <li> If a word ends with #2, for example \'To#2\', this means a second word with the same
       spelling, so just ignore the #2 part when translating. This is necessary since in some
       languages \'to\' is translated differently depending on the context (e.g. \'bis\' or
       \'an\' in german). Some words may end with #short. Often used in tables, they have to
       be translated with the shorter abbreviation of the word. For example, \'days#short\'
       and \'hours#short\' are translated in english by \'d\' and \'h\', as you can see them
       in the \'Time remaining\' column of the status page (e.g. \'12d 8h\').
</ul>
</td></tr>
</table>
<p></p>
';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');


   {
      $translator_set = @$player_row['Translator'];
      if( !$translator_set )
        error('not_translator');
      $translator_array = explode( LANG_TRANSL_CHAR, $translator_set);
   }


   $translate_lang = get_request_arg('translate_lang');
   if( ALLOW_PROFIL_CHARSET && ($player_row['admin_level'] & ADMIN_TRANSLATORS) )
     $profil_charset = (int)(bool)@$_REQUEST['profil_charset'];
   else
     $profil_charset = 0;

   $group = get_request_arg('group');
   $untranslated = (int)(bool)@$_REQUEST['untranslated'];
   $alpha_order = (int)(bool)@$_REQUEST['alpha_order'];
   if( TRANSL_ALLOW_FILTER )
      $filter_en = get_request_arg('filter_en');
   else
      $filter_en = '';
   $no_pages = (int)(bool)@$_REQUEST['no_pages'];
   if( $no_pages )
      $from_row = -1;
   else
      $from_row = max(0,(int)@$_REQUEST['from_row']);


if(0){//old
   $translation_groups =
      array( 'Common', 'Start', 'Game', 'Messages', 'Users', 'Forum'
           , 'Docs', 'FAQ', 'Admin', 'Error', 'Countries'
           );
   $translation_groups =
      array_value_to_key_and_value( $translation_groups);
}else{//new
   $result = mysql_query(
            "SELECT Groupname FROM TranslationGroups" )
      or error('internal_error', 'translate.groups_query');
   $translation_groups = array();
   while( ($row = mysql_fetch_row($result)) )
      $translation_groups[$row[0]] = $row[0];
   mysql_free_result( $result);
}//old/new
   $translation_groups['allgroups'] = 'All groups';

   if( !$group or !array_key_exists( $group, $translation_groups) )
   {
      $group = 'allgroups';
      $untranslated = 1;
   }


   if( count( $translator_array ) > 1 )
   {
      $lang_choice = true;
   }
   else
   {
      $lang_choice = false;
      if( !$translate_lang && count( $translator_array ) == 1 )
      {
         $translate_lang = $translator_array[0];
      }
   }

   if( $translate_lang )
   {
      if( !in_array( $translate_lang, $translator_array ) )
         error('not_correct_transl_language');

      $result = translations_query( $translate_lang, $untranslated, $group
               , $from_row, $alpha_order, $filter_en)
         or error('mysql_query_failed','translate.translation_query');

      $show_rows = (int)@mysql_num_rows($result);
      if( !TRANSL_ALLOW_FILTER && $show_rows <= 0 && !$untranslated )
         error('translation_bad_language_or_group','translat1');

      $lang_string = '';
      foreach( $known_languages as $browsercode => $array )
      {
         foreach( $array as $charenc => $langname )
         {
            if( $browsercode . LANG_CHARSET_CHAR . $charenc == $translate_lang)
               $lang_string .= ",$langname";
         }
      }
      if( $lang_string )
         $lang_string = substr( $lang_string, 1);
      else
         $lang_string = $translate_lang;

      @list(,$translate_encoding) = explode( LANG_CHARSET_CHAR, $translate_lang, 2);

      if( !$profil_charset )
         $encoding_used = $translate_encoding; // before start_page()

      $lang_string.= ' / ' . $translate_encoding;
      if( ALLOW_PROFIL_CHARSET )
        $lang_string.=  ' / ' . $encoding_used;
   }


   $page = 'translate.php';
   $page_hiddens = array();
   if( $translate_lang )
      $page_hiddens['translate_lang'] = $translate_lang;
   if( $profil_charset )
      $page_hiddens['profil_charset'] = 1;
   if( $group )
      $page_hiddens['group'] = $group;
   if( $untranslated )
      $page_hiddens['untranslated'] = 1;
   if( $alpha_order )
      $page_hiddens['alpha_order'] = 1;
   if( $filter_en )
      $page_hiddens['filter_en'] = $filter_en;
   if( $from_row > 0 )
      $page_hiddens['from_row'] = $from_row;

   $tabindex= 1;

   start_page(T_("Translate"), true, $logged_in, $player_row);
   echo "<CENTER>\n";
   $str = 'Read this before translating';
   if( (bool)@$_REQUEST['infos'] )
   {
      echo '<h3 class=Header>' . $str . ':</h3>';
      echo $info_box;
   }
   else
   {
      $tmp = $page_hiddens;
      $tmp['infos'] = 1;
      echo '<h3 class=Header>' . anchor( make_url($page, $tmp), $str) . '</h3>';
   }


   if( $translate_lang )
   {
      $nbcol = 3;
      { //$translate_form
      $translate_form = new Form( 'translateform', 'update_translation.php', FORM_POST );
      $translate_form->add_row( array(
                  'CELL', $nbcol, 'align="center"', //set $nbcol for the table
                  'HEADER', 'Translate the following strings' ) );

      $translate_form->add_row( array( 'CELL', $nbcol, 'align="center"', 'TEXT', "- $lang_string -" ) );

      $table_links = '';
      $tmp = $page_hiddens;
      if( !$no_pages && $show_rows > 0 && $from_row > 0 )
      {
         $tmp['from_row'] = max(0, $from_row-TRANS_ROW_PER_PAGE);
         if( $table_links )
            $table_links.= '&nbsp;|&nbsp;';
         $table_links.= anchor( make_url($page, $tmp),
               T_('Prev Page'), '', array('accesskey' => '<'));
      }
      if( !$no_pages && $show_rows > TRANS_ROW_PER_PAGE )
      {
         $show_rows = TRANS_ROW_PER_PAGE;
         $tmp['from_row'] = $from_row+TRANS_ROW_PER_PAGE;
         if( $table_links )
            $table_links.= '&nbsp;|&nbsp;';
         $table_links.= anchor( make_url($page, $tmp),
               T_('Next Page'), '', array('accesskey' => '>'));
      }

      if( $table_links )
      {
         //$table_links = '<div class=LinksR>'.$table_links.'</div>';
         $translate_form->add_row( array( 'CELL', $nbcol
               , 'align="right" bgcolor="#d0d0d0"', 'TEXT', $table_links ) );
      }

      $translate_form->add_row( array( 'HR' ) ); //$nbcol

      $oid= -1;
      while( ($row = mysql_fetch_assoc($result)) && $show_rows-- > 0 )
      {
        /* see the translations_query() function for the constraints
         * on the "ORDER BY" clause associated with this "$oid" filter:
         */
         if( $oid == $row['Original_ID'] ) continue;
         $oid = $row['Original_ID'];

         $string = $row['Original'];
         $hsize = 60;
         $vsize = intval(floor(min( max( 2,
                                         strlen( $string ) / $hsize + 2,
                                         substr_count( $string, "\n" ) + 2 ),
                                    12 )));

         $translation = $row['Text'];
         $translation = textarea_safe( $translation, $translate_encoding);
         $form_row = array( 'TEXT', nl2br( textarea_safe($string, LANG_DEF_CHARSET ) ),
                            'TD',
                            'TEXTAREA', "transl" . $row['Original_ID'],
                              $hsize, $vsize, $translation,
                            'CELL', 1, 'nowrap',
                            'CHECKBOX', 'same' . $row['Original_ID'], 'Y',
                              'untranslated', $row['Text'] === '',
                           ) ;
         /*
            Unchanged box is useful when, for instance, a FAQ entry receive
            a minor correction that does not involve a translation modification.
            Else one can't remove the entry from the untranslated group.
         */
         if( $untranslated && !empty($translation))
            array_push( $form_row,
                            'BR',
                            'CHECKBOX', 'unch' . $row['Original_ID'], 'Y',
                              'unchanged', false ) ;

         $translate_form->add_row( $form_row, -1, false ) ;

         $translate_form->add_row( array( 'HR' ) ); //$nbcol
      }
      mysql_free_result( $result);

      if( $oid > 0 ) //empty table
      {
         if( $table_links )
         {
            $translate_form->add_row( array( 'CELL', $nbcol
                  , 'align="right" bgcolor="#d0d0d0"', 'TEXT', $table_links ) );
         }

         $translate_form->add_row( array( 'SPACE' ) ); //$nbcol
         $translate_form->add_row( array(
            'CELL', $nbcol, 'align="center"',
            'HIDDEN', 'translate_lang', $translate_lang,
            'HIDDEN', 'profil_charset', $profil_charset,
            'HIDDEN', 'group', $group,
            'HIDDEN', 'untranslated', $untranslated,
            'HIDDEN', 'alpha_order', $alpha_order,
            'HIDDEN', 'filter_en', $filter_en,
            'HIDDEN', 'from_row', $from_row,
            'SUBMITBUTTONX', 'apply_changes', 'Apply translation changes to Dragon',
               array('accesskey'=>'x'),
            ) );
      }

      $translate_form->echo_string($tabindex);
      $tabindex= $translate_form->tabindex;
      } //$translate_form

      $nbcol = 1;
      $groupchoice_form = new Form( 'selectgroupform', $page, FORM_POST );
      $groupchoice_form->add_row( array(
         'HEADER', 'Groups',
         ) ); //$nbcol

      $groupchoice_form->add_row( array(
//         'DESCRIPTION', 'Change to group',
         'CELL', $nbcol, 'align="center"',
         'SELECTBOX', 'group', 1, $translation_groups, $group, false,
         'HIDDEN', 'translate_lang', $translate_lang,
         'HIDDEN', 'profil_charset', $profil_charset,
         'HIDDEN', 'from_row', 0,
         'SUBMITBUTTONX', 'just_group', 'Just change group',
            array('accesskey'=>'w'),
         'CHECKBOX', 'untranslated', 1, 'untranslated', $untranslated,
         'CHECKBOX', 'alpha_order', 1, 'alpha order', $alpha_order,
         ) );
      if( TRANSL_ALLOW_FILTER )
         $groupchoice_form->add_row( array(
            'CELL', $nbcol, 'align="center"',
            'TEXT', 'English filter (_:any char, %:any number of chars, \:escape)',
            'TEXTINPUT', 'filter_en', 20, 80, $filter_en,
            ) );

      $groupchoice_form->echo_string($tabindex);
      $tabindex= $groupchoice_form->tabindex;
   }

   if( $lang_choice )
   {
      $nbcol = 1;
      $langchoice_form = new Form( 'selectlangform', $page, FORM_POST );
      $langchoice_form->add_row( array(
         'HEADER', 'Select language to translate to',
         ) ); //$nbcol

      $lang_desc = get_language_descriptions_translated( true);
      $vals = array();
      foreach( $lang_desc as $lang => $langname )
      {
           @list( $browsercode, $charenc) = explode( LANG_CHARSET_CHAR, $lang, 2);
           if( in_array( $lang, $translator_array ) or
               in_array( $browsercode, $translator_array ) )
              $vals[$lang] = $langname;
      }

      $langchoice_form->add_row( array(
         'CELL', $nbcol, 'align="center"',
         'SELECTBOX', 'translate_lang', 1, $vals, $translate_lang, false,
         'HIDDEN', 'group', $group,
         'HIDDEN', 'untranslated', $untranslated,
         'HIDDEN', 'alpha_order', $alpha_order,
         'HIDDEN', 'filter_en', $filter_en,
         'HIDDEN', 'from_row', 0,
         'SUBMITBUTTON', 'cl', 'Select',
         ) );

      if( ALLOW_PROFIL_CHARSET )
         $langchoice_form->add_row( array(
            'CELL', $nbcol, 'align="center"',
            'CHECKBOX', 'profil_charset', 1, 'use profile encoding', $profil_charset,
            ) );

      $langchoice_form->echo_string($tabindex);
   }

   echo "</CENTER>\n";
   end_page();
}

?>
