<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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

// translations removed for this page: $TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/filter_functions.php" );
require_once( "include/make_translationfiles.php" );
$ThePage = new Page('Translate');

define('ALLOW_PROFIL_CHARSET', 1); //allow the admins to overwrite the page encoding
define('TRANSL_ALLOW_FILTER', 1); //allow a search on the english phrases


$info_box = '<br>When translating you should keep in mind the following things:
<ul>
  <li> You must enter your translation in the second column boxes.
       <br>
       The first column displays the english original phrases in a similar way
       but is read-only.
       <br>
       The third part displays the english original phrases in the HTML way as a
       display example. Caution: this example may sometime differs from the effective
       display in the normal page because of additional constraints of the normal page.
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
  <li> In some strings there are html tags (enclosed by \'&lt;\' and \'&gt;\')
       or entities (starting with a \'&amp;\', ended by a \';\').
       If you don\'t know how to use html code, just copy the original code and
       change the real language. If you are unsure, you can use the translator
       forum to get help.
  <li> If you want to change the html code in some way in the translation, keep in mind
       that the code shall conform to the standard layout of Dragon.
       <br>
       To not introduce unwanted html elements, use
       &amp;lt; instead of &lt;, &amp;gt; instead of &gt; and &amp;amp; instead of &amp;,
       if you need to display those characters in your translated string.
  <li> Inside your messages, posts and some other places of DGS, you may use our private
       pseudo-html tags. Those pseudo-tags (like &lt;home ...&gt;, &lt;color ...&gt;)
       need a special decoding step from our own code to work, and so, they are,
       most of the time, unusable in the translated strings.
       <br>
       For facilities, we have added the decoding step of our pseudo-tags to the FAQ
       entries (caution: this is not the whole FAQ translation group).
       So you can use them here (or copy thoses used by the FAQ maintainers).
       For instance, for links homed at DGS, use not &lt;a href="..."&gt;, but the
       home-tag, i.e. &lt;home users.php&gt;Users&lt;/home&gt;: this will make the
       FAQ entries portable to an other site.
       <br>
       The FAQ maintainers also have the possibility to use the note-tag
       &lt;note&gt;(removed from entry)&lt;/note&gt; to add some notes seen only
       by them and by the translators. Read these notes then... copy them to or remove
       them from your translated string... they will not be displayed in the FAQ pages.
  <li> If a word ends with #2 or #note (without space), for example \'To#2\' or \'All#msg\',
       this means a second word with the same spelling, so just ignore the #2 part when
       translating. This is necessary since in some languages \'to\' is translated differently
       depending on the context (e.g. \'bis\' or \'an\' in german).
       Some words may end with #short. Often used in tables, they have to be translated
       with the shorter abbreviation of the word.
       For example, \'days#short\' and \'hours#short\' are translated in english by \'d\' and
       \'h\', as you can see them in the \'Time remaining\' column of the status page
       (e.g. \'12d 8h\').
       <br>
       The other purpose is to provide a way to manage different wording or abbreviations
       for the same words, e.g. #header for some title in tables.
</ul>';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');


   $lang_desc = get_language_descriptions_translated( /*T_*/true);
   if( TRANS_FULL_ADMIN
      && (@$player_row['admin_level'] & ADMIN_TRANSLATORS)
      )
   {
      $translator_array = array_keys( $lang_desc);
   }
   else
   {
      $translator_set = @$player_row['Translator'];
      if( !$translator_set )
        error('not_translator');
      $translator_array = explode( LANG_TRANSL_CHAR, $translator_set);
   }


   $translate_lang = get_request_arg('translate_lang');
   if( ALLOW_PROFIL_CHARSET
      && (@$player_row['admin_level'] & ADMIN_TRANSLATORS)
      )
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
   ksort( $translation_groups);
   $translation_groups['allgroups'] = 'All groups';

   if( !$group || !array_key_exists( $group, $translation_groups) )
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

      @list($browsercode,$translate_encoding) = explode( LANG_CHARSET_CHAR, $translate_lang, 2);

      if( !$profil_charset )
         $encoding_used = $translate_encoding; // before start_page()

      $lang_string.= ' / ' . $browsercode;
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

   start_page(/*T_*/('Translate'), true, $logged_in, $player_row);
   $str = /*T_*/('Read this before translating');
   if( (bool)@$_REQUEST['infos'] )
   {
      echo "<h3 class=Header>$str:</h3>\n"
         . "<table class=InfoBox><tr><td>\n"
         . $info_box
         . "\n</td></tr></table>\n";
   }
   else
   {
      $tmp = $page_hiddens;
      $tmp['infos'] = 1;
      $tmp = anchor( make_url($page, $tmp), $str);
      echo "<h3 class=Header>$tmp</h3>\n";
   }


   if( $translate_lang )
   {
      $nbcol = 3;
      { //$translate_form
      $translate_form = new Form( 'translate', 'update_translation.php', FORM_POST );
      $translate_form->add_row( array(
                  'CELL', $nbcol, '', //set $nbcol for the table
                  'HEADER', /*T_*/('Translate the following strings') ) );

      $translate_form->add_row( array(
                  'CELL', $nbcol, '',
                  'TEXT', "- $lang_string -" ) );

      $table_links = '';
      $tmp = $page_hiddens;
      if( !$no_pages && $show_rows > 0 && $from_row > 0 )
      {
         $tmp['from_row'] = max(0, $from_row-TRANS_ROW_PER_PAGE);
         if( $table_links )
            $table_links.= '&nbsp;|&nbsp;';
         $table_links.= anchor( make_url($page, $tmp),
               /*T_*/('Prev Page'), '', array( 'accesskey' => ACCKEY_ACT_PREV ));
      }
      if( !$no_pages && $show_rows > TRANS_ROW_PER_PAGE )
      {
         $show_rows = TRANS_ROW_PER_PAGE;
         $tmp['from_row'] = $from_row+TRANS_ROW_PER_PAGE;
         if( $table_links )
            $table_links.= '&nbsp;|&nbsp;';
         $table_links.= anchor( make_url($page, $tmp),
               /*T_*/('Next Page'), '', array( 'accesskey' => ACCKEY_ACT_NEXT ));
      }

      if( $table_links )
      {
         $translate_form->add_row( array( 'ROW', 'LinksT',
               'CELL', 1, 'class=PageLinksL',
               'TEXT', $table_links,
               'CELL', $nbcol-1, 'class=PageLinksR',
               'TEXT', $table_links,
                ) );
      }

      if( $filter_en != '' )
      {
         $rx_term = implode('|', sql_extract_terms( $filter_en ));
      }
      else
         $rx_term = '';

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
         $translation = $row['Text'];

         //$debuginfo must be html_safe.
         if( (@$player_row['admin_level'] & ADMIN_DEVELOPER) /* && @$_REQUEST['debug'] */ )
            $debuginfo = "<br><span class=DebugInfo>"
               . "L=".$row['Language_ID'].",G=".$row['Group_ID']
               . ",T=$oid [".$row['Translated'].'/'.$row['Translatable'].']'
               . "</span>";
         else
            $debuginfo = '';

         $hsize = 60;
         $vsize = intval( floor( max( 2,
                     substr_count( wordwrap( $translation, $hsize, "\n", 1), "\n" ),
                     substr_count( wordwrap( $string, $hsize, "\n", 1), "\n" )
                  )));

         //insert the rx_term highlights as if it was 'faq' (lose) item
         $sample = make_html_safe($string, 'faq', $rx_term);

         //execute the textarea_safe() here because of the various_encoding
         $string = textarea_safe($string, 'iso-8859-1'); //LANG_DEF_CHARSET);
         $translation = textarea_safe( $translation, $translate_encoding);

         //both textareas in OWNHTML because of previous textarea_safe()
         $form_row = array(
                  'CELL', 1, 'class=English',
                  'OWNHTML', "<textarea name=\"orgen$oid\" readonly" //readonly disabled
                     . " cols=\"$hsize\" rows=\"$vsize\">"
                     . $string."</textarea>"
                     . $debuginfo, //already html_safe, maybe empty
                  'CELL', 1, 'class=Language',
                  'OWNHTML', "<textarea name=\"transl$oid\""
                     . " cols=\"$hsize\" rows=\"$vsize\">"
                     . $translation."</textarea>",
                  'BR', 'CHECKBOX', "same$oid", 'Y',
                           /*T_*/('untranslated'), $row['Text'] === '',
                  ) ;
         /*
            Unchanged box is useful when, for instance, a FAQ entry receive
            a minor correction that does not involve a translation modification.
            Else one can't remove the entry from the untranslated group.
         */
         if( $row['Translated'] === 'N' ) //exclude not yet translated items
            array_push( $form_row,
                  'TEXT', '&nbsp;&nbsp;',
                  'CHECKBOX', "unch$oid", 'Y', /*T_*/('unchanged'), false
                  ) ;

         // allow some space on the right
         if( $nbcol > 2 )
            array_push( $form_row, 'CELL', $nbcol-2, '');

         $translate_form->add_row( $form_row);

         $translate_form->add_row( array(
                  'CELL', $nbcol, 'class=Sample',
                  'TEXT', $sample //already html_safe
                  ) );

         $translate_form->add_row( array( 'HR' ) ); //$nbcol
      }
      mysql_free_result( $result);

      if( $table_links )
      {
         $translate_form->add_row( array( 'ROW', 'LinksB',
               'CELL', 1, 'class=PageLinksL',
               'TEXT', $table_links,
               'CELL', $nbcol-1, 'class=PageLinksR',
               'TEXT', $table_links,
                ) );
      }

      if( $oid > 0 ) //not empty table
      {
         $translate_form->add_row( array( 'SPACE' ) ); //$nbcol
         $translate_form->add_row( array( 'ROW', 'SubmitTransl',
            'CELL', $nbcol, '',
            'HIDDEN', 'translate_lang', $translate_lang,
            'HIDDEN', 'profil_charset', $profil_charset,
            'HIDDEN', 'group', $group,
            'HIDDEN', 'untranslated', $untranslated,
            'HIDDEN', 'alpha_order', $alpha_order,
            'HIDDEN', 'filter_en', $filter_en,
            'HIDDEN', 'from_row', $from_row,
            'SUBMITBUTTONX', 'apply_changes',
               /*T_*/('Apply translation changes to Dragon'),
               array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
            ) );
      }

      $translate_form->echo_string($tabindex);
      $tabindex= $translate_form->tabindex;
      } //$translate_form

      $nbcol = 1;
      $groupchoice_form = new Form( 'selectgroup', $page, FORM_POST );
      $groupchoice_form->add_row( array(
         'HEADER', /*T_*/('Groups'),
         ) ); //$nbcol

      if( TRANSL_ALLOW_FILTER )
         $groupchoice_form->add_row( array(
            'CELL', $nbcol, '',
            'TEXT', /*T_*/('English filter (_:any char, %:any number of chars, \:escape)&nbsp;'),
            'TEXTINPUT', 'filter_en', 20, 80, $filter_en,
            ) );
      $groupchoice_form->add_row( array(
//         'DESCRIPTION', /*T_*/('Change to group'),
         'CELL', $nbcol, '',
         'SELECTBOX', 'group', 1, $translation_groups, $group, false,
         'HIDDEN', 'translate_lang', $translate_lang,
         'HIDDEN', 'profil_charset', $profil_charset,
         'HIDDEN', 'from_row', 0,
         'SUBMITBUTTONX', 'just_group', /*T_*/('Just change group'),
            array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
         'CHECKBOX', 'untranslated', 1,
            /*T_*/('untranslated'), $untranslated,
         'CHECKBOX', 'alpha_order', 1,
            /*T_*/('alpha order'), $alpha_order,
         ) );

      $groupchoice_form->echo_string($tabindex);
      $tabindex= $groupchoice_form->tabindex;
   }

   if( $lang_choice )
   {
      $nbcol = 1;
      $langchoice_form = new Form( 'selectlang', $page, FORM_POST );
      $langchoice_form->add_row( array(
         'HEADER', /*T_*/('Select language to translate to'),
         ) ); //$nbcol

      $vals = array();
      foreach( $lang_desc as $lang => $langname )
      {
           @list( $browsercode, $charenc) = explode( LANG_CHARSET_CHAR, $lang, 2);
           if( in_array( $lang, $translator_array ) ||
               in_array( $browsercode, $translator_array ) )
              $vals[$lang] = $langname;
      }

      $langchoice_form->add_row( array(
         'CELL', $nbcol, '',
         'SELECTBOX', 'translate_lang', 1, $vals, $translate_lang, false,
         'HIDDEN', 'group', $group,
         'HIDDEN', 'untranslated', $untranslated,
         'HIDDEN', 'alpha_order', $alpha_order,
         'HIDDEN', 'filter_en', $filter_en,
         'HIDDEN', 'from_row', 0,
         'SUBMITBUTTON', 'cl', /*T_*/('Select'),
         ) );

      if( ALLOW_PROFIL_CHARSET
         && (@$player_row['admin_level'] & ADMIN_TRANSLATORS)
         )
         $langchoice_form->add_row( array( 'ROW', 'DebugInfo',
            'CELL', $nbcol, '',
            'CHECKBOX', 'profil_charset', 1,
               /*T_*/('use profile encoding'), $profil_charset,
            ) );

      $langchoice_form->echo_string($tabindex);
   }

   end_page();
}
?>
