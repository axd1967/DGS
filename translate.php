<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony, Jens-Uwe Gaspar

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

// translations remove for admin page: $TranslateGroups[] = "Admin";

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/filter_functions.php';
require_once 'include/make_translationfiles.php';

$GLOBALS['ThePage'] = new Page('Translate');

define('ALLOW_PROFIL_CHARSET', 1); //allow the admins to overwrite the page encoding

$info_box = '<br>When translating you should keep the following things in mind:
<ul>
  <li> You must <b>enter your translation</b> in the second column boxes.
       <br>
       The first column displays the <b>english original</b> phrases in a similar way
       but is read-only.
       <br>
       The text below the english original phrases shows the <b>preview</b> of how the text
       looks on the site, while the text below the textarea in the 2nd column
       shows the preview of the translation-text.
       <br>
       <b>Caution:</b> this preview examples may sometime differ from the effective
       display in the normal page because of additional constraints of the normal page.
       <br><br>
  <li> Below the english phrase you can also see a <b>date of the last-change</b> (if present).
       <br>
       The date is shown in red color if the text is new or has been updated and need
       a re-translation.
       <br><br>
  <li> If a translated <b>word is the same</b> as in english, leave it blank and click
       the \'untranslated\' box to the right.
       <br><br>
  <li> If you need to know the <b>context</b> of a phrase to translate (where it appears on DGS),
       you can ask in the translator forum for help.
       <br><br>
  <li> If a text ends with <b>#label</b> (without space and only alpha-numeric chars),
       for example \'To#2\' or \'All#msg\', this is an alternate text with the same spelling,
       so just ignore the #label part when translating.
       <br>
       This was necessary since in some languages \'to\' is translated differently
       depending on the context (e.g. \'bis\' or \'an\' in german), or the case is written
       differently (e.g. \'Edit#2\' vs \'edit\').
       <br>
       Currently there are over 100 different labels in use. To list them all would be too
       much, but the label often gives a hint to the texts context, so can be of help
       in translating.
       <br>
       This is often used in tables, where words have to be translated with a shorter
       abbreviation of the word to make the table column more narrow.
       For example, \'days#short\' and \'hours#short\' are translated in english by \'d\' and
       \'h\', as you can see them in the \'Time remaining\' column of the status page
       (e.g. \'12d 8h\').
       <br><br>
  <li> In some places there is a <b>percent-character</b> followed by some characters.
       This is a special place where the program might put some data in.
       <br>
       Example: \'with %s extra per move\' might be displayed as \'with 2 hours extra per move\'.
       <br>
       If you want to <b>change the order</b> of these you can use \'%1$s\' to place to make
       sure that you get the first argument and \'%2$s\' for the second etc.
       <br>
       <a href="http://www.php.net/manual/en/function.sprintf.php">You can read more here</a>
       <br><br>
  <li> In some strings there are <b>html tags</b> (enclosed by \'&lt;\' and \'&gt;\')
       or entities (starting with a \'&amp;\', ended by a \';\').
       If you don\'t know how to use html code, just copy the original code and
       change the real language. If you are unsure, you can use the translator
       forum to get help.
       <br><br>
  <li> If you want to change the html code in some way in the translation, keep in mind
       that the code shall conform to the standard layout of Dragon.
       <br>
       Do not introduce <b>unwanted html</b> elements, use
       &amp;lt; instead of &lt;, &amp;gt; instead of &gt; and &amp;amp; instead of &amp;,
       if you need to display those characters in your translated string.
       <br><br>
  <li> Inside your messages, posts and some other places of DGS, you may use our private
       <b>pseudo-html</b> tags. Those pseudo-tags (like &lt;home ...&gt;, &lt;color ...&gt;)
       need a special decoding step from our own code to work, and so, they are,
       most of the time, unusable in the translated strings.
       <br>
       For facilities, we have added the decoding step of our pseudo-tags to the FAQ
       entries (caution: this is not the whole FAQ translation group).
       So you can use them here (or copy thoses used by the FAQ maintainers).
       For instance, for links homed at DGS, use not &lt;a href="..."&gt;, but the
       home-tag, i.e. &lt;home users.php&gt;Users&lt;/home&gt;: this will make the
       FAQ entries portable to an other site and is shorter.
       <br>
       The FAQ maintainers also have the possibility to use the note-tag
       &lt;note&gt;(removed from entry)&lt;/note&gt; to add some notes seen only
       by them and by the translators. Read these notes then... copy them to or remove
       them from your translated string... they will not be displayed in the FAQ pages.
</ul>';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_NO_QUOTA_HIT );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'translate');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'translate');

   $lang_desc = get_language_descriptions_translated(true);
   if ( TRANS_FULL_ADMIN && (@$player_row['admin_level'] & ADMIN_TRANSLATORS))
      $translator_array = array_keys( $lang_desc);
   else
   {
      $translator_set = @$player_row['Translator'];
      if ( !$translator_set )
         error('not_translator', 'translate');
      $translator_array = explode( LANG_TRANSL_CHAR, $translator_set);
   }


   $translate_lang = get_request_arg('translate_lang');
   if ( ALLOW_PROFIL_CHARSET && (@$player_row['admin_level'] & ADMIN_TRANSLATORS) )
      $profil_charset = (int)@$_REQUEST['profil_charset'];
   else
      $profil_charset = 0;

   $group = get_request_arg('group');
   $untranslated = (int)@$_REQUEST['untranslated']; // see translation_query()
   $alpha_order = (int)@$_REQUEST['alpha_order'];
   $filter_en = trim(get_request_arg('filter_en'));
   $max_len = (int)@$_REQUEST['max_len'];
   if ( $max_len < 0 )
      $max_len = 0;
   $no_pages = (int)@$_REQUEST['no_pages'];
   if ( $no_pages )
      $from_row = -1;
   else
      $from_row = max(0,(int)@$_REQUEST['from_row']);

   $result = db_query( 'translate.groups_query', "SELECT Groupname FROM TranslationGroups" );
   $translation_groups = array();
   while ( ($row = mysql_fetch_row($result)) )
      $translation_groups[$row[0]] = $row[0];
   mysql_free_result( $result);
   ksort( $translation_groups);
   $translation_groups['allgroups'] = 'All groups';

   if ( !$group || !array_key_exists( $group, $translation_groups) )
   {
      $group = 'allgroups';
      $untranslated = 1; // default: show untranslated
   }

   if ( count( $translator_array ) > 1 )
      $lang_choice = true;
   else
   {
      $lang_choice = false;
      if ( !$translate_lang && count( $translator_array ) == 1 )
         $translate_lang = $translator_array[0];
   }

   $is_save = @$_REQUEST['save'];
   $is_preview = @$_REQUEST['preview'];

   $show_rows = $found_rows = 0;
   if ( $translate_lang )
   {
      if ( !in_array( $translate_lang, $translator_array ) )
         error('not_correct_transl_language', "translate.check.language($translate_lang)");

      $result = translations_query( $translate_lang, $untranslated, $group, $from_row, $alpha_order, $filter_en, $max_len)
         or error('mysql_query_failed','translate.translation_query');

      $show_rows = (int)@mysql_num_rows($result);
      if ( $show_rows > 0 )
         $found_rows = mysql_found_rows('translate.found_rows');
   }

   if ( $is_save )
   {
      if ( $show_rows > TRANS_ROW_PER_PAGE )
         $show_rows = TRANS_ROW_PER_PAGE;
      update_translation( $translate_lang, $result, $show_rows );

      jump_to("translate.php?translate_lang=".urlencode($translate_lang) .
         ($profil_charset ? URI_AMP."profil_charset=".$profil_charset : '') .
         URI_AMP."group=".urlencode($group) .
         ($untranslated ? URI_AMP."untranslated=$untranslated" : '') .
         ($alpha_order ? URI_AMP."alpha_order=$alpha_order" : '') .
         ($filter_en ? URI_AMP."filter_en=".urlencode($filter_en) : '') .
         ($max_len ? URI_AMP."max_len=".urlencode($max_len) : '') .
         ($from_row > 0 ? URI_AMP."from_row=$from_row" : '') );
   }


   $language_name = '';
   if ( $translate_lang )
   {
      $lang_string = '';
      foreach ( $known_languages as $browsercode => $array )
      {
         foreach ( $array as $charenc => $langname )
         {
            if ( $browsercode . LANG_CHARSET_CHAR . $charenc == $translate_lang)
               $lang_string .= ",$langname";
         }
      }
      if ( $lang_string )
         $lang_string = substr( $lang_string, 1);
      else
         $lang_string = $translate_lang;
      $language_name = $lang_string;

      @list($browsercode,$translate_encoding) = explode( LANG_CHARSET_CHAR, $translate_lang, 2);

      if ( !$profil_charset )
         $encoding_used = $translate_encoding; // before start_page()

      $lang_string.= ' / ' . $browsercode;
      $lang_string.= ' / ' . $translate_encoding;
      if ( ALLOW_PROFIL_CHARSET )
        $lang_string.=  ' / ' . $encoding_used;
   }

   $arr_translated = array(
         1 => 'Untranslated (in original texts)',
         2 => 'Translated (in original texts)',
         0 => 'All texts (in original texts)',
      );
   if ( $language_name )
      $arr_translated[3] = sprintf( 'Translated (in %s texts)', $language_name );


   $page = 'translate.php';
   $page_hiddens = array();
   if ( $translate_lang )
      $page_hiddens['translate_lang'] = $translate_lang;
   if ( $profil_charset )
      $page_hiddens['profil_charset'] = 1;
   if ( $group )
      $page_hiddens['group'] = $group;
   if ( $untranslated )
      $page_hiddens['untranslated'] = $untranslated;
   if ( $alpha_order )
      $page_hiddens['alpha_order'] = 1;
   if ( $filter_en )
      $page_hiddens['filter_en'] = $filter_en;
   if ( $max_len )
      $page_hiddens['max_len'] = $max_len;
   if ( $from_row > 0 )
      $page_hiddens['from_row'] = $from_row;

   $tabindex= 1;

   start_page(T_('Translate'), true, $logged_in, $player_row);
   $str = T_('Read this before translating');
   if ( @$_REQUEST['infos'] )
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


   if ( $translate_lang )
   {
      $nbcol = 2;

      $translate_form = new Form( 'translate', 'translate.php', FORM_POST );
      $translate_form->add_row( array(
                  'CELL', $nbcol, '', //set $nbcol for the table
                  'HEADER', T_('Translate the following strings') ) );

      $translate_form->add_row( array(
                  'CELL', $nbcol, '',
                  'TEXT', "- $lang_string -" ) );

      // see also class Table.make_next_prev_links()
      $curr_page = floor($from_row / TRANS_ROW_PER_PAGE) + 1;
      if ( $from_row > ($curr_page - 1) * TRANS_ROW_PER_PAGE )
         $curr_page++;
      if ( $found_rows >= 0 )
      {
         $max_page = floor( ($found_rows + TRANS_ROW_PER_PAGE - 1) / TRANS_ROW_PER_PAGE );
         if ( $from_row > ($max_page - 1) * TRANS_ROW_PER_PAGE )
            $max_page++;
      }
      else
         $max_page = 0;

      $table_links = '';
      $align = 'align=bottom'; //'align=middle'
      $tmp = $page_hiddens;
      if ( !$no_pages )
      {
         if ( $from_row > 0 ) // start-link
         {
            $tmp['from_row'] = 0;
            $table_links .= anchor( make_url($page, $tmp),
               image( $base_path.'images/start.gif', '|<=', '', $align), T_('first page') );
         }
         if ( $show_rows > 0 && $from_row > 0 ) // prev-link
         {
            $tmp['from_row'] = max(0, $from_row-TRANS_ROW_PER_PAGE);
            if ( $table_links )
               $table_links.= MED_SPACING;
            $table_links .= anchor( make_url($page, $tmp),
                  image( $base_path.'images/prev.gif', '<=', '', $align),
                  T_('Prev Page'), '', array( 'accesskey' => ACCKEY_ACT_PREV ));
         }

         $table_links .= SMALL_SPACING . sprintf( T_('Page %s of %s#tablenavi'), $curr_page, $max_page ) . SMALL_SPACING;

         if ( $show_rows > TRANS_ROW_PER_PAGE )
         {
            $show_rows = TRANS_ROW_PER_PAGE;
            $tmp['from_row'] = $from_row+TRANS_ROW_PER_PAGE;
            $table_links .= anchor( make_url($page, $tmp),
                  image( $base_path.'images/next.gif', '=>', '', $align),
                  T_('Next Page'), array( 'accesskey' => ACCKEY_ACT_NEXT ));

            if ( $found_rows > TRANS_ROW_PER_PAGE ) // end-link
            {
               $tmp['from_row'] = floor( $found_rows / TRANS_ROW_PER_PAGE) * TRANS_ROW_PER_PAGE;
               if ( $table_links )
                  $table_links.= MED_SPACING;
               $table_links .= anchor( make_url($page, $tmp),
                    image( $base_path.'images/end.gif', '=>|', '', $align), T_('last page') );
            }
         }
      }

      $table_entries = ( $found_rows > 0 ) ? sprintf('(%s entries to translate)', $found_rows) : '';
      if ( $table_links || $table_entries )
      {
         $translate_form->add_row( array( 'ROW', 'LinksT',
               'CELL', 1, 'class=PageLinksL',
               'TEXT', $table_links . SMALL_SPACING . $table_entries,
               'CELL', $nbcol-1, 'class=PageLinksR',
               'TEXT', $table_links,
            ));
      }

      $rx_term = ( (string)$filter_en != '' )
         ? implode('|', sql_extract_terms( $filter_en ))
         : '';

      $translate_form->add_row( array( 'HR' ) );

      $oid= -1;
      while ( ($row = mysql_fetch_assoc($result)) && $show_rows-- > 0 )
      {
         /* see the translations_query() function for the constraints
          * on the "ORDER BY" clause associated with this "$oid" filter:
          */
         if ( $oid == $row['Original_ID'] )
            continue;
         $oid = $row['Original_ID'];

         $orig_string = $row['Original'];
         if ( $is_preview )
         {
            $translation = trim( get_request_arg("transl$oid") );
            $transl_untranslated = ( @$_REQUEST["same$oid"] === 'Y' );
            $transl_unchanged = ( @$_REQUEST["unch$oid"] === 'Y' );
         }
         else
         {
            $translation = $row['Text'];
            $transl_untranslated = ( $row['Text'] === '' );
            $transl_unchanged = false;
         }

         //$debuginfo must be html_safe.
         if ( (@$player_row['admin_level'] & ADMIN_DEVELOPER) /* && @$_REQUEST['debug'] */ )
            $debuginfo = "<br><span class=\"DebugInfo smaller\">"
               . "L=".$row['Language_ID']
               . ", G=".$row['Group_ID']
               . ", TP=".$row['Type']
               . ", ST=".$row['Status']
               . ", T=$oid [".$row['Translated'].'/'.$row['Translatable'].']'
               . "</span>";
         else
            $debuginfo = '';

         $orig_updinfo = ( $row['TT_Updated'] )
            ? ( $row['TT_Updated'] > $row['T_Updated']
                  ? span("smaller UpdTransl", '(Updated: ' . date(DATE_FMT6, $row['TT_Updated']) . ')')
                  : span('smaller', '(Last change: ' . date(DATE_FMT6, $row['TT_Updated']) . ')')
              )
            : '';
         $transl_updinfo = ( $row['T_Updated'] )
            ? span('smaller', '(Last change: ' . date(DATE_FMT6, $row['T_Updated']) . ')')
            : '';

         $hsize = 70;
         $vsize = intval( floor( max( 2,
                     substr_count( wordwrap( $translation, $hsize, "\n", 1), "\n" ) + 1,
                     substr_count( wordwrap( $orig_string, $hsize, "\n", 1), "\n" ) + 1
                  )));

         //insert the rx_term highlights as if it was 'faq' (lose) item
         $orig_preview = make_html_safe( strip_translation_label($orig_string), 'faq', ( $untranslated != 3 ? $rx_term : '' ));
         $translation_preview = make_html_safe($translation, 'faq', ( $untranslated == 3 ? $rx_term : '' ));

         //execute the textarea_safe() here because of the various_encoding
         $orig_string = textarea_safe( $orig_string, 'iso-8859-1'); //LANG_DEF_CHARSET);
         $translation = textarea_safe( $translation, $translate_encoding);

         //both textareas in OWNHTML because of previous textarea_safe()
         $form_row = array(
               'CELL', 1, 'class=English',
               'OWNHTML', "<textarea name=\"orgen$oid\" readonly" //readonly disabled
                  . " cols=\"$hsize\" rows=\"$vsize\">"
                  . $orig_string."</textarea>"
                  . trim($debuginfo . ' ' . $orig_updinfo),
               'CELL', 1, 'class=Language',
               'OWNHTML', "<textarea name=\"transl$oid\""
                  . " cols=\"$hsize\" rows=\"$vsize\">"
                  . $translation."</textarea>",
               'BR', 'CHECKBOX', "same$oid", 'Y', T_('untranslated'), $transl_untranslated,
            );
         /*
            Unchanged box is useful when, for instance, a FAQ entry receive
            a minor correction that does not involve a translation modification.
            Else one can't remove the entry from the untranslated group.
         */
         if ( $row['Translated'] === 'N' ) //exclude not yet translated items
            array_push( $form_row,
                  'TEXT', MED_SPACING,
                  'CHECKBOX', "unch$oid", 'Y', T_('unchanged'), $transl_unchanged
               );

         array_push( $form_row,
               'TEXT', SMALL_SPACING . $transl_updinfo
            );

         // allow some space on the right
         if ( $nbcol > 2 )
            array_push( $form_row, 'CELL', $nbcol-2, '');

         $translate_form->add_row( $form_row);

         $translate_form->add_row( array(
               'CELL', 1, 'class=Sample',
               'TEXT', $orig_preview, //already html_safe
               'CELL', 1, 'class=Sample',
               'TEXT', $translation_preview, //already html_safe
            ));

         $translate_form->add_row( array( 'HR' ) );
      }
      mysql_free_result( $result);

      if ( $table_links )
      {
         $translate_form->add_row( array( 'ROW', 'LinksB',
               'CELL', 1, 'class=PageLinksL',
               'TEXT', $table_links,
               'CELL', $nbcol-1, 'class=PageLinksR',
               'TEXT', $table_links,
             ));
      }

      if ( $oid > 0 ) //not empty table
      {
         $translate_form->add_row( array( 'SPACE' ) );
         $translate_form->add_row( array( 'ROW', 'SubmitTransl',
               'CELL', $nbcol, '',
               'HIDDEN', 'translate_lang', $translate_lang,
               'HIDDEN', 'profil_charset', $profil_charset,
               'HIDDEN', 'group', $group,
               'HIDDEN', 'untranslated', $untranslated,
               'HIDDEN', 'alpha_order', $alpha_order,
               'HIDDEN', 'filter_en', $filter_en,
               'HIDDEN', 'max_len', $max_len,
               'HIDDEN', 'from_row', $from_row,
               'SUBMITBUTTONX', 'save',
                  T_('Apply translation changes to Dragon'),
                  array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
               'TEXT', SMALL_SPACING.SMALL_SPACING,
               'SUBMITBUTTONX', 'preview',
                  T_('Preview translations'),
                  array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            ));
      }

      $translate_form->echo_string($tabindex);
      $tabindex = $translate_form->get_tabindex();

      // end of $translate_form


      $nbcol = 1;
      $groupchoice_form = new Form( 'selectgroup', $page, FORM_POST );
      $groupchoice_form->add_row( array(
            'HEADER', T_('Groups'),
         )); //$nbcol

      $groupchoice_form->add_row( array(
            'CELL', $nbcol, '',
            'TEXT', T_('Filter (_:any char, %:any number of chars, \:escape)&nbsp;'),
            'TEXTINPUT', 'filter_en', 20, 80, $filter_en,
            'TEXT', sptext(T_('with max. length'), 1),
            'TEXTINPUT', 'max_len', 4, 4, (int)@$_REQUEST['max_len'],
         ));
      if ( $language_name )
      {
         $groupchoice_form->add_row( array(
               'CELL', $nbcol, '',
               'TEXT', sprintf( T_('Searching with selection [%s] is case-sensitive!'), $arr_translated[3] ) ));
      }
      $groupchoice_form->add_row( array(
//         'DESCRIPTION', T_('Change to group'),
            'CELL', $nbcol, '',
            'SELECTBOX', 'group', 1, $translation_groups, $group, false,
            'HIDDEN', 'translate_lang', $translate_lang,
            'HIDDEN', 'profil_charset', $profil_charset,
            'HIDDEN', 'from_row', 0,
            'SELECTBOX', 'untranslated', 1, $arr_translated, $untranslated, false,
            'SUBMITBUTTON', 'just_group', T_('Show texts'),
            'TEXT', MED_SPACING,
            'CHECKBOX', 'alpha_order', 1, T_('alpha order'), $alpha_order,
         ));

      $groupchoice_form->echo_string($tabindex);
      $tabindex = $groupchoice_form->get_tabindex();
   }

   // show language-choice for admin or translator with >1 language
   if ( $lang_choice )
   {
      $nbcol = 1;
      $langchoice_form = new Form( 'selectlang', $page, FORM_POST );
      $langchoice_form->add_row( array(
            'HEADER', T_('Select language to translate to') )); //$nbcol

      $vals = array();
      foreach ( $lang_desc as $lang => $langname )
      {
         @list( $browsercode, $charenc) = explode( LANG_CHARSET_CHAR, $lang, 2);
         if ( in_array( $lang, $translator_array ) || in_array( $browsercode, $translator_array ) )
            $vals[$lang] = $langname;
      }
      asort($vals);

      $langchoice_form->add_row( array(
            'CELL', $nbcol, '',
            'SELECTBOX', 'translate_lang', 1, $vals, $translate_lang, false,
            'HIDDEN', 'group', $group,
            'HIDDEN', 'untranslated', $untranslated,
            'HIDDEN', 'alpha_order', $alpha_order,
            'HIDDEN', 'filter_en', $filter_en,
            'HIDDEN', 'max_len', $max_len,
            'HIDDEN', 'from_row', 0,
            'SUBMITBUTTON', 'cl', T_('Select'),
         ));

      if ( ALLOW_PROFIL_CHARSET && (@$player_row['admin_level'] & ADMIN_TRANSLATORS) )
      {
         $langchoice_form->add_row( array( 'ROW', 'DebugInfo',
               'CELL', $nbcol, '',
               'CHECKBOX', 'profil_charset', 1, T_('use profile encoding'), $profil_charset,
            ));
      }

      $langchoice_form->echo_string($tabindex);
   }


   $menu = array( T_('Translation-Statistics') => "translation_stats.php" );

   end_page($menu);
}//main


// save translations
function update_translation( $transl_lang, $result, $show_rows )
{
   global $NOW, $player_row;

   $replace_set = '';
   $log_set = '';
   $done_set = '';
   $oid= -1;
   while ( ($row = mysql_fetch_assoc($result)) && $show_rows-- > 0 )
   {
      /* see the translations_query() function for the constraints
       * on the "ORDER BY" clause associated with this "$oid" filter:
       */
      if ( $oid == $row['Original_ID'] )
         continue;
      $oid = $row['Original_ID'];
      $tlangID = (int)@$row['Language_ID'];

      $translation = trim(get_request_arg("transl$oid"));
      $same = ( @$_REQUEST["same$oid"] === 'Y' );
      $unchanged = ( @$_REQUEST["unch$oid"] === 'Y' );


      if ( $unchanged && $row['Translated'] === 'N' ) //exclude not yet translated items
      { //unchanged item
         //UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN
         if ( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
            $done_set .= ",$oid";

         $translation = ( $same ) ? '' : mysql_addslashes($translation);

         //REPLACE INTO Translations (Original_ID,Language_ID,Text,Translated,Updated)
         $replace_set .= ",($oid,$tlangID,'$translation','Y',FROM_UNIXTIME($NOW))";

         //no $log_set
      }
      elseif ( ( $same && $row['Text'] !== '' ) || ( !empty($translation) && $row['Text'] !== $translation ) )
      { //same or modified item
         //UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN
         if ( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
            $done_set .= ",$oid";

         $translation = ( $same ) ? '' : mysql_addslashes($translation);

         //REPLACE INTO Translations (Original_ID,Language_ID,Text,Translated,Updated)
         $replace_set .= ",($oid,$tlangID,'$translation','Y',FROM_UNIXTIME($NOW))";

         //INSERT INTO Translationlog (Player_ID,Original_ID,Language_ID,Translation)
         $log_set .= ',(' . $player_row['ID'] . ",$oid,$tlangID,'$translation')";
      }
   } //foreach translation phrases

   ta_begin();
   {//HOT-section to update translations
      if ( $replace_set )
      {
         // NOTE: Translations needs PRIMARY KEY (Language_ID,Original_ID):
         db_query( 'translate.update_translation.replace',
            "REPLACE INTO Translations " .
               "(Original_ID,Language_ID,Text,Translated,Updated) VALUES " . substr($replace_set,1) );
      }

      if ( $log_set )
      {
         db_query( 'translate.update_translation.log',
            "INSERT INTO Translationlog " .
               "(Player_ID,Original_ID,Language_ID,Translation) VALUES " . substr($log_set,1) ); //+ Date= timestamp
      }

      if ( $done_set )
      {
         db_query( 'translate.update_translation.done',
            "UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN (" . substr($done_set,1) . ')' );
      }
   }
   ta_end();

   make_include_files($transl_lang); //must be called from main dir
}//update_translation

?>
