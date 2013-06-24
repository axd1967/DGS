<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/std_classes.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/make_translationfiles.php';
require_once 'include/faq_functions.php';
require_once 'include/admin_faq_functions.php';
require_once 'include/filter_parser.php';

$GLOBALS['ThePage'] = new Page('FAQAdmin');


$info_box = '<ul>
  <li> For the texts in FAQ only edit in charset iso-8859-1.
       Characters in the range 0x80-FF will be replaced by HTML entities.
  <li> Hidden entries - marked by \'(H)\' - are not shown in the live-FAQ
       and disappear from the translators\' lists.
  <li> You may toggle the \'translatable\' status of an entry with the right
       side button. But as soon as one translator had translated it, this
       button will disappear and you will have to use the inside check-box(es)
       (\'Mark as changed for translators\') to signal to the translators
       when your edition is finished and that they have some work to do again.
       Please, avoid to check the box if you have just fixed a typo ;)
       This button is also not shown as long as the entry is hidden.
  <li> You may delete an entry by emptying its text-box(es)... while it had
       not been translated. Then you will only be allowed to hide it, waiting
       to re-use it later.
  <li> When adding a new entry or editing an existing entry, hit the
       \'Preview\' button to see how the FAQ-entry will look like
       without saving it.
  <li> For links homed at DGS, don\'t use &lt;a href=".."&gt;, but the
       home-tag, e.g. &lt;home users.php&gt;Users&lt;/home&gt;
  <li> You may use the note-tag &lt;note&gt;(removed from entry)&lt;/note&gt;
       to add some notes seen only by faq admins and translators.
  <li> Setting the move distance allows to control how
       many lines an entry can be moved up or down. A saved value is
       stored for one hour in a cookie. Invalid or 0 resets to the
       default of 1.
  <li> The \'Search\' finds text in Question or Answers of all
       categories and texts, also in the hidden ones. The search-term
       is implicitly prefixed and suffixed with a wildcard \'%\'-pattern,
       so is always a substring-search. Found entries will be marked with
       a red \'<font color="red">#</font>\'.
  <li> The last used entry is marked with a blue \'<font color="blue"><b>&gt;</b></font>\'.
</ul>';

/*
 * This page is used to edit three different sets of entries:
   - table FAQ with entries for faq.php
   - table Intro with entries for introduction.php
   - table Links with entries for links.php

   - all three tables share the same field-structure in the database,
     even so the meaning of the fields is a bit different:
     - FAQ:   Question / Answer, Reference (unused)
     - Intro: Question (header-text), Answer (description), Reference (unused)
     - Links: Question (link-text), Answer (extra-description), Reference (link-URL)

 * Translatable flag meaning - See also translate.php
  Value   : Meaning             : admin_toggle : admin_mark_box    : translator_page
 ---------:---------------------:--------------:-------------------:-------------------
  N       : not translatable    : change to Y  : -                 : -
  Y       : to be translated    : change to N  : -                 : change to Done
  Done    : already translated  : -            : change to Changed : -
  Changed : to be re-translated : -            : -                 : change to Done

  When both Q & A (if any) are not translated (both Translatable == ('Y' or 'N'))
   - admin_toggle is enabled
   - admin_mark_box is disabled
   - Answer.Translatable is always equal to Question.Translatable
  When one of them have already been translated (any Translatable(s) != ('Y' or 'N'))
   - admin_toggle is disabled
   - Q & A are independently translated
     and reversed to *be re-translated* by the way of the admin_mark_box
   - Answer.Translatable may be different of Question.Translatable

  When an admin change a Translatable flag to 'Changed'
   - all the *language* Translated flags are set to 'N'
  As soon as one translator change a *language* Translated flag to 'Y'
   - the Translatable flag is set to 'Done'
*/

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_NO_QUOTA_HIT );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'admin_faq');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_faq');
   if ( !(@$player_row['admin_level'] & ADMIN_FAQ) )
      error('adminlevel_too_low', 'admin_faq');

/* Actual REQUEST calls used:
     # actions
     back&id=&ot=&view=&qterm= : back to list (jump to pos), see handling-above
     move=u|d&dir=&id=        : move entry up/down (on same level, within category), dir=move-dist
     move=uu|dd&id=           : move entry up/down (on parent level, other category)
     toggleH=Y|N&id=          : toggle hidden-state of entry to Y (hide) or N (show)
     toggleT=Y|N&id=          : toggle translatable-state of entry to Y (translatable) or N
     edit&type=c|e&id=        : open editor for entry of TYPE (e=entry, c=category), optional preview
         &preview=&question=&answer=&reference=
     do_edit&type=c|e&id=     : save edited entry of TYPE (e=entry, c=category)
         &question=&answer=&reference=
     new&type=c|e&id=         : open editor for new entry of TYPE (e=entry, c=category), optional preview
         &preview=&question=&answer=&reference=
     do_new&type=c|e&id=      : save new entry of TYPE (e=entry, c=category)
         &question=&answer=&reference=

     # parameters
     ot           : object-type TXTOBJTYPE_INTRO|LINKS|FAQ
     view         : category to view: '' (sections only), 'all' (all sections expanded), id (category-id to expand)
     dir          : [int] number of steps to move (<0 or >0)
     id           : FAQ.ID
     movedist     : [int] steps to move on moving entries up or down
     preview      : '' (no preview), 't' (show preview)
     qterm        : search term (in question or answer)
     type         : 'e' = entry, 'c' = category
     question     : question-text
     answer       : answer-text
     reference    : reference-text
*/

   $fid = max(0, (int)@$_REQUEST['id']);
   $sql_term = get_request_arg('qterm', '');
   $url_term = ( (string)$sql_term != '' ) ? URI_AMP.'qterm='.urlencode($sql_term) : '';

   $view = get_request_arg('view', 0); // ''|0, all, [int]
   if ( $url_term )
      $view = 'all'; // enforce expand-all on term-search
   $view_all = ( $view === 'all' );
   $view = (int)$view;
   if ( $view < 0 ) $view = 0;
   $view_val = ($view_all) ? 'all' : $view; // used in form-fields

   $objtype = (int)get_request_arg('ot');
   if ( $objtype == TXTOBJTYPE_INTRO )
   {
      $dbtable = $tr_group = 'Intro';
      $adm_title = 'Introduction';
      $label_head = 'Title';
      $label_cont = 'Description';
      $label_ref = '';
      $rows_cont = 20;
   }
   elseif ( $objtype == TXTOBJTYPE_LINKS )
   {
      $dbtable = $adm_title = $tr_group = 'Links';
      $label_head = 'Link Text';
      $label_cont = 'Description';
      $label_ref = 'Reference (URL)';
      $rows_cont = 3;
   }
   else // FAQ
   {
      $dbtable = $adm_title = $tr_group = 'FAQ';
      $label_head = 'Question';
      $label_cont = 'Answer';
      $label_ref = '';
      $rows_cont = 20;
   }

   // read/write move-distance for entries using cookie
   $movedist = (int)@$_REQUEST['movedist'];
   if ( $movedist < 1 )
      $movedist = max( 1, (int)safe_getcookie('admin_faq_movedist'));
   if ( @$_REQUEST['setmovedist'] ) // write into cookie
      safe_setcookie( 'admin_faq_movedist', $movedist, SECS_PER_HOUR ); // save for 1h

   $show_list = true;
   $page_base = "admin_faq.php?ot=$objtype";
   $page = $page_base . URI_AMP.'view='.$view_val;


   // ***********        Move entry       ****************

   $errors = array();
   // args: id, move=u|d, dir=length of the move (int, pos or neg)
   if ( ($action=@$_REQUEST['move']) == 'u' || $action == 'd' )
   {
      $dir = isset($_REQUEST['dir']) ? (int)$_REQUEST['dir'] : 1;
      $dir = ($action == 'd') ? $dir : -$dir; //because ID top < ID bottom
      AdminFAQ::move_faq_entry_same_level( "admin_faq($action)", $dbtable, $fid, $dir );
      jump_to($page.URI_AMP."id=$fid#e$fid"); //clean URL
   } //move


   // ***********        Move entry to new category      ****************

   // args: id, move=uu|dd
   elseif ( ($action=@$_REQUEST['move']) == 'uu' || $action == 'dd' )
   {
      $page = $page_base . URI_AMP.'view=all'; // expand all categories
      AdminFAQ::move_faq_entry_to_new_category( "admin_faq($action)", $dbtable, $fid, ($action == 'dd' ? 1 : -1 ) );
      jump_to($page.URI_AMP."id=$fid#e$fid"); //clean URL
   } //bigmove


   // ***********       Toggle hidden       ****************

   // args: id, toggleH=Y|N
   elseif ( ($action=@$_REQUEST['toggleH']) )
   {
      AdminFAQ::toggle_hidden_faq_entry( "admin_faq", $dbtable, $fid );
      //jump_to($page); //clean URL
   } //toggleH


   // ***********       Toggle translatable       ****************

   // args: id, toggleT=Y|N
   elseif ( ($action=@$_REQUEST['toggleT']) )
   {
      $row = AdminFAQ::get_faq_entry_row( $dbtable, $fid );
      $transl = AdminFAQ::transl_toggle_state($row);
      if ( !$transl )
         jump_to($page.URI_AMP.'sysmsg='.urlencode('Error: entry already translated'));

      if ( ($action=='Y') xor ($transl == 'Y') )
      {
         AdminFAQ::toggle_translatable_faq_entry( "admin_faq", $dbtable, $row, $transl );
         make_include_files(null, $tr_group); //must be called from main dir
      }
      //jump_to($page); //clean URL
   } //toggleT


   // ***********        Edit entry       ****************

   // args: id, edit=t, type=c|e, question, answer, reference  [ preview=1, qterm=sql_term ]
   // keep it tested before 'do_edit' for the preview feature
   elseif ( @$_REQUEST['edit'] && ( ($action=@$_REQUEST['type']) == 'c' ||  $action == 'e' ) )
   {
      if ( $action == 'c' )
         $title = $adm_title.' Admin - Edit category';
      else
         $title = $adm_title.' Admin - Edit entry';
      start_page($title, true, $logged_in, $player_row );
      echo "<h3 class=Header>$title</h3>\n";

      $show_list = false;

      $row = AdminFAQ::get_faq_entry_row( $dbtable, $fid );
      $faqhide = ( @$row['Hidden'] == 'Y' );

      if ( @$_REQUEST['preview'] )
      {
         $question = trim( get_request_arg('question') );
         $answer = trim( get_request_arg('answer') );
         $reference = trim( get_request_arg('reference') );
         $question = latin1_safe($question);
         $answer = latin1_safe($answer);
      }
      else
      {
         $question = $row['Q'];
         $answer = $row['A'];
         $reference = $row['Reference'];
      }
      if ( $action == 'e' )
         check_reference( $errors, $objtype, $reference );

      $edit_form = new Form('faqeditform', $page.URI_AMP."id=$fid#e$fid", FORM_POST );

      if ( count($errors) )
      {
         $edit_form->add_row( array( 'DESCRIPTION', T_('Errors'),
                                     'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
         $edit_form->add_empty_row();
      }

      $q_updated = ( $row['QUpdated'] ) ? date(DATE_FMT, $row['QUpdated']) : NO_VALUE;
      if ( $row['Level'] == 1 ) //i.e. Category
      {
         //$edit_form->add_row( array( 'HEADER', 'Edit category' ) );
         $edit_form->add_row( array( 'DESCRIPTION', 'Category',
                                     'TEXTINPUT', 'question', 80, 80, $question ) );
         $edit_form->add_row( array( 'TAB',
                                     'CHECKBOXX', 'Qchanged', 'Y',
                                     sprintf( 'Mark entry as changed for translators (Last change: %s)', $q_updated ),
                                     get_request_arg('Qchanged', false),
                                     array( 'disabled' => ( $faqhide || $row['QTranslatable'] !== 'Done' ) ) ));
      }
      else //i.e. Question/Answer/Reference
      {
         $a_updated = ( $row['AUpdated'] ) ? date(DATE_FMT, $row['AUpdated']) : NO_VALUE;

         //$edit_form->add_row( array( 'HEADER', "Edit $adm_title entry" ) );
         if ( $label_ref )
         {
            $edit_form->add_row( array( 'DESCRIPTION', $label_ref,
                                        'TEXTINPUT', 'reference', 80, 255, $reference ) );
         }
         $edit_form->add_row( array( 'DESCRIPTION', $label_head,
                                     'TEXTINPUT', 'question', 80, 80, $question ) );
         $edit_form->add_row( array( 'TAB',
                                     'CHECKBOXX', 'Qchanged', 'Y',
                                     sprintf( 'Mark entry as changed for translators (Last change: %s)', $q_updated ),
                                     get_request_arg('Qchanged', false),
                                     array( 'disabled' => ( $faqhide || $row['QTranslatable'] !== 'Done' ) ) ));
         $edit_form->add_row( array( 'DESCRIPTION', $label_cont,
                                     'TEXTAREA', 'answer', 80, $rows_cont, $answer ) );
         $edit_form->add_row( array( 'TAB',
                                     'CHECKBOXX', 'Achanged', 'Y',
                                     sprintf( 'Mark entry as changed for translators (Last change: %s)', $a_updated ),
                                     get_request_arg('Achanged', false),
                                     array( 'disabled' => ( $faqhide || $row['ATranslatable'] !== 'Done' ) ) ));
      }

      $edit_form->add_row( array(
            'HIDDEN', 'type', $action,
            'HIDDEN', 'view', $view_val,
            'HIDDEN', 'preview', 1,
            'HIDDEN', 'qterm', textarea_safe($sql_term),
            'SUBMITBUTTONX', 'do_edit', 'Save entry',
                  array( 'accesskey' => ACCKEY_ACT_EXECUTE, 'disabled' => count($errors) ),
            'SUBMITBUTTONX', 'edit', 'Preview',
                  array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            'SUBMITBUTTON', 'back', 'Back to list',
            ));
      $edit_form->echo_string(1);

      $rx_term = ( @$_REQUEST['preview'] ) ? '' : implode('|', sql_extract_terms( $sql_term ));
      show_preview( $row['Level'], $question, $answer, $reference, "e$fid", $rx_term);
   } //edit


   // ***********        Save edited entry       ****************

   // args: id, do_edit=t type=c|e, question, answer, reference  [ preview='', qterm=sql_term ]
   // keep it tested after 'edit' for the preview feature
   elseif ( @$_REQUEST['do_edit'] && ( ($action=@$_REQUEST['type']) == 'c' ||  $action == 'e' ) )
   {
      $question = trim( get_request_arg('question') );
      $answer = trim( get_request_arg('answer') );
      $reference = trim( get_request_arg('reference') );

      $row = AdminFAQ::get_faq_entry_row( $dbtable, $fid );

      $log = 0;
      $ref_id = $fid; // anchor-ref

      // Delete or update ?
      if ( !$question && !$answer && !$reference )
      { // Delete
         if ( !AdminFAQ::transl_toggle_state($row) ) //can't be toggled
            jump_to($page.URI_AMP."sysmsg=".urlencode('Error: entry already translated'));

         if ( !AdminFAQ::delete_faq_entry( "admin_faq", $dbtable, $fid, $row ) )
            jump_to($page.URI_AMP."sysmsg=".urlencode('Error: category not empty'));

         $ref_id = $row['Parent']; // anchor-ref to former, parent category
      }
      else
      { //Update
         if ( count($errors) || !$question || ($objtype == TXTOBJTYPE_LINKS && $action == 'e' && !$reference) )
            jump_to($page.URI_AMP."sysmsg=".urlencode('Error: an entry must be given'));

         $log = AdminFAQ::update_faq_entry( "admin_faq", $dbtable, $fid, $row,
            ( @$_REQUEST['Qchanged'] === 'Y' ), //only if not hidden
            ( @$_REQUEST['Achanged'] === 'Y' ), //only if not hidden
            $question, $answer, $reference );
      }

      if ( $log & 0x7 ) //i.e. modified except deleted, 0x8=reference (not translated)
      {
         if ( $row['QTranslatable'] !== 'N' || ( $row['Answer'] > 0 && $row['ATranslatable'] !== 'N' ) )
            make_include_files(null, $tr_group); //must be called from main dir
      }

      jump_to( $page.URI_AMP."id={$ref_id}{$url_term}#e$ref_id" );
      // overview-loading takes so long, so refresh edit-page
      //jump_to( $page.URI_AMP."edit=1".URI_AMP."type=$action".URI_AMP."id=$ref_id$url_term"
         //. URI_AMP.'sysmsg='.urlencode('Entry saved!') );
   } //do_edit


   // ***********        New entry       ****************

   // args: id, new=t, type=c|e, question, answer, reference  [ do_new=?, preview=t ]
   // keep it tested before 'do_new' for the preview feature
   elseif ( @$_REQUEST['new'] && ( ($action=@$_REQUEST['type']) == 'c' ||  $action == 'e' ) )
   {
      if ( $action == 'c' )
         $title = $adm_title.' Admin - New category';
      else
         $title = $adm_title.' Admin - New entry';
      start_page($title, true, $logged_in, $player_row );
      echo "<h3 class=Header>$title</h3>\n";

      $show_list = false;

      if ( @$_REQUEST['preview'] )
      {
         $question = trim( get_request_arg('question') );
         $answer = trim( get_request_arg('answer') );
         $reference = trim( get_request_arg('reference') );
         $question = latin1_safe($question);
         $answer = latin1_safe($answer);
      }
      else
      {
         $question = '';
         $answer = '';
         $reference = '';
      }
      if ( $action == 'e' )
         check_reference( $errors, $objtype, $reference );

      $edit_form = new Form('faqnewform', $page.URI_AMP."id=$fid#e$fid", FORM_POST );

      if ( count($errors) )
      {
         $edit_form->add_row( array( 'DESCRIPTION', T_('Errors'),
                                     'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
         $edit_form->add_empty_row();
      }

      if ( $action == 'c' )
      {
         //$edit_form->add_row( array( 'HEADER', 'New category' ) );
         $edit_form->add_row( array( 'DESCRIPTION', 'Category',
                                     'TEXTINPUT', 'question', 80, 80, $question ) );
      }
      else
      {
         //$edit_form->add_row( array( 'HEADER', 'New entry' ) );
         if ( $label_ref )
         {
            $edit_form->add_row( array( 'DESCRIPTION', $label_ref,
                                        'TEXTINPUT', 'reference', 80, 255, $reference ) );
         }
         $edit_form->add_row( array( 'DESCRIPTION', $label_head,
                                     'TEXTINPUT', 'question', 80, 80, $question ) );
         $edit_form->add_row( array( 'DESCRIPTION', $label_cont,
                                     'TEXTAREA', 'answer', 80, $rows_cont, $answer ) );
      }

      $edit_form->add_row( array(
            'HIDDEN', 'type', $action,
            'HIDDEN', 'preview', 1,
            'HIDDEN', 'view', $view_val,
            'SUBMITBUTTONX', 'do_new', 'Add entry',
                  array( 'accesskey' => ACCKEY_ACT_EXECUTE, 'disabled' => count($errors) ),
            'SUBMITBUTTONX', 'new', 'Preview',
                  array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
            'SUBMITBUTTON', 'back', 'Back to list',
            ));
      $edit_form->echo_string(1);

      show_preview( ($action=='c' ? 1 : 2), $question, $answer, $reference, "e$fid");
   } //new


   // ***********        Save new entry       ****************

   // args: id, do_new=t type=c|e, question, answer, reference  [ preview='' ]
   // keep it tested after 'new' for the preview feature
   elseif ( @$_REQUEST['do_new'] && ( ($action=@$_REQUEST['type']) == 'c' ||  $action == 'e' ) )
   {
      $question = trim( get_request_arg('question') );
      $answer = trim( get_request_arg('answer') );
      $reference = trim( get_request_arg('reference') );

      if ( !$question || ($objtype == TXTOBJTYPE_LINKS && $action == 'e' && !$reference) )
         jump_to($page.URI_AMP."sysmsg=".urlencode('Error: an entry must be given'));

      $new_id = AdminFAQ::save_new_faq_entry( 'admin_faq', $dbtable, $tr_group, $fid, ($action == 'c'),
         $question, $answer, $reference );

      jump_to( $page.URI_AMP."id=$ref_id#e$ref_id" ); //clean URL (focus on new entry)
      // overview-loading takes so long, so redirect to edit-page
      //jump_to( $page.URI_AMP."edit=1".URI_AMP."type=$action".URI_AMP."id=$new_id$url_term"
         //. URI_AMP.'sysmsg='.urlencode('Entry saved!') );
   } //do_new



   // ***********       Show whole list       ****************

   if ( $show_list )
   {
      $title = $adm_title.' Admin';
      start_page($title, true, $logged_in, $player_row );

      $str = 'Read this before editing';
      if ( (bool)@$_REQUEST['infos'] )
      {
         echo "<h3 class=Header>$str:</h3>\n"
            . "<table class=InfoBox><tr><td>\n"
            . $info_box
            . "\n</td></tr></table>\n";
      }
      else
      {
         $tmp = anchor( $page.URI_AMP.'infos=1', $str);
         echo "<h3 class=Header>$tmp</h3>\n";
      }


      // FAQ-search
      $search_form = new Form('faqsearchform', $page, FORM_POST );
      $search_form->add_row( array(
            'DESCRIPTION',  'Search Term',
            'TEXTINPUT',    'qterm', 30, -1, $sql_term,
            'SUBMITBUTTONX', 'search', 'Search',
                        array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
            ));
      $search_form->add_row( array(
            'TAB',
            'TEXT', '(_=any char, %=any number of chars, \=escape char)' ));
      $search_form->add_row( array(
            'DESCRIPTION',  'Move distance',
            'TEXTINPUT',    'movedist', 4, 2, $movedist,
            'SUBMITBUTTON', 'setmovedist', 'Set move length' ));
      $search_form->add_hidden( 'id',  $fid ); // current entry
      $search_form->echo_string(1);


      //build comparison with implicit wildcards
      if ( $view_all )
         $view_qpart = 'E.Level IN (1,2)';
      elseif ( $view > 0 )
         $view_qpart = "E.Level=1 OR (E.Level=2 AND E.Parent=$view)";
      else
         $view_qpart = 'E.Level=1';

      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'E.*', 'Question.Text AS Q',
            'Question.Translatable AS QTranslatable',
            'Answer.Translatable AS ATranslatable',
            'IF(E.Level=1,E.SortOrder,PE.SortOrder) AS CatOrder',
         SQLP_FROM, "$dbtable AS E",
            "INNER JOIN $dbtable AS PE ON PE.ID=E.Parent",
            'INNER JOIN TranslationTexts AS Question ON Question.ID=E.Question',
            'LEFT JOIN TranslationTexts AS Answer ON Answer.ID=E.Answer',
         SQLP_WHERE,
            $view_qpart,
         SQLP_ORDER,
            'CatOrder', 'E.Level', 'E.SortOrder' );
      if ( $sql_term )
      {
         $term_like = "LIKE '".mysql_addslashes("%$sql_term%")."'";
         $qsql->add_part( SQLP_FIELDS,
            "IF(Question.Text $term_like,1,0) AS MatchQuestion",
            "IF(E.Level>1 AND Answer.Text $term_like,1,0) AS MatchAnswer" );
            // old try with a binary field:
            //"IF(LOWER(Question.Text) $term_like,1,0) AS MatchQuestion",
            //"IF(E.Level>1 AND LOWER(Answer.Text) $term_like,1,0) AS MatchAnswer",
      }
      else
         $qsql->add_part( SQLP_FIELDS, '0 AS MatchQuestion', '0 AS MatchAnswer' );
      $result = db_query( 'admin_faq.list', $qsql->get_select() );


      echo "<h3 class=Header>$title</h3>\n";

      $nbcol = 12;
      echo name_anchor('general'), "<table class=FAQAdmin>\n";

      // table-columns:
      // curr-entry | match-term | Q/New | A | move-up | ~down | cat-up | ~down | New | Hide | Transl

      if ( !$view_all )
         echo "<tr><td colspan=2></td>",
            TD_button( 'Expand all categories', $page_base.URI_AMP.'view=all', 'images/expand.gif', ''),
            '<td colspan=2>Expand all categories</td>',
            '<td colspan=', ($nbcol-5), '></td></tr>', "\n";
      if ( $view_all || $view )
         echo "<tr><td colspan=2></td>",
            TD_button( 'Collapse all categories', $page_base.URI_AMP.'view=0', 'images/collapse.gif', ''),
            '<td colspan=2>Collapse all categories</td>',
            '<td colspan=', ($nbcol-5), '></td></tr>', "\n";
      echo "<tr><td>&nbsp;</td><tr>\n";

      echo "<tr><td colspan=2></td>", //for marks
         TD_button( 'Add new category',
               $page.URI_AMP."new=1".URI_AMP."type=c".URI_AMP."id=1",
               'images/new.png', 'N'),
         '<td colspan=2>(first category)</td>',
         '<td colspan=', ($nbcol-5), '></td></tr>', "\n";

      while ( $row = mysql_fetch_assoc( $result ) )
      {
         $question = (empty($row['Q']) ? '(empty)' : $row['Q']);
         $faqhide = ( @$row['Hidden'] == 'Y' );
         $transl = AdminFAQ::transl_toggle_state($row);
         $eid = $row['ID'];
         $entry_ref = "#e$eid";

         // mark 'current' entry and matched-terms (2 cols)
         echo '<tr><td>';
         if ( $row['MatchQuestion'] || $row['MatchAnswer'] )
            echo '<font color="red">#</font>';
         echo '</td><td>';
         if ( $fid == $eid )
            echo '<font color="blue"><b>&gt;</b></font>';
         echo '</td>';

         // anchor-label + td-start for cat/entry
         if ( $row['Level'] == 1 )
         {
            echo '<td class=Category colspan=3><a name="e'.$eid.'"></a>',
               anchor( $page_base.URI_AMP."view=$eid", image( 'images/expand.gif', 'E', 'Expand category', 'class=InTextImage' )),
               MINI_SPACING;
            $typechar = 'c'; //category
         }
         else
         {
            echo '<td class=Indent></td>'
               . '<td class=Entry colspan=2><a name="e'.$eid.'"></a>';
            $typechar = 'e'; //entry
         }

         // question/answer (1 col)
         if ( $faqhide )
            echo "(H) ";
         echo "<A href=\"$page".URI_AMP."edit=1".URI_AMP."type=$typechar".URI_AMP."id=$eid"
                  ."$url_term\" title=\"Edit\">$question</A>";
         echo "\n</td>";

         // move entry up/down (focus parent category)
         echo TD_button( 'Move up',
               $page.URI_AMP."move=u".URI_AMP."id=$eid".URI_AMP."dir={$movedist}$entry_ref",
               'images/up.png', 'u');
         echo TD_button( 'Move down',
               $page.URI_AMP."move=d".URI_AMP."id=$eid".URI_AMP."dir={$movedist}$entry_ref",
               'images/down.png', 'd');

         if ( $row['Level'] > 1 )
         {
            // move entry up/down to other category (focus current entry)
            echo TD_button( 'Move to previous category',
                  $page.URI_AMP."move=uu".URI_AMP."id=$eid$entry_ref",
                  'images/up_up.png', 'U');
            echo TD_button( 'Move to next category',
                  $page.URI_AMP."move=dd".URI_AMP."id=$eid$entry_ref",
                  'images/down_down.png', 'D');
         }
         else
            echo '<td colspan=2></td>';

         // new entry
         echo TD_button( ($typechar == 'e' ? 'Add new entry' : 'Add new category'),
               $page.URI_AMP."new=1".URI_AMP."type=$typechar".URI_AMP."id=$eid",
               'images/new.png', 'N');

         // hide (focus parent category)
         if ( $faqhide )
            echo TD_button( 'Show',
                  $page.URI_AMP."toggleH=N".URI_AMP."id=$eid$entry_ref",
                  'images/hide_no.png', 'h');
         else
            echo TD_button( 'Hide',
                  $page.URI_AMP."toggleH=Y".URI_AMP."id=$eid$entry_ref",
                  'images/hide.png', 'H');

         // translatable (focus parent category)
         if ( !$faqhide && $transl )
         {
            if ( $transl == 'Y' )
               echo TD_button( 'Make untranslatable',
                     $page.URI_AMP."toggleT=N".URI_AMP."id=$eid$entry_ref",
                     'images/transl.png', 'T');
            else
               echo TD_button( 'Make translatable',
                     $page.URI_AMP."toggleT=Y".URI_AMP."id=$eid$entry_ref",
                     'images/transl_no.png', 't');
         }
         else
            echo '<td></td>';

         echo '</tr>';

         // new category (below section-title)
         if ( $row['Level'] == 1 && ($view_all || $view == $eid) )
            echo "<tr><td colspan=2></td><td class=Indent></td>",
               TD_button( 'Add new entry',
                  $page.URI_AMP."new=1".URI_AMP."type=e".URI_AMP."id=$eid",
                  'images/new.png', 'N'),
               '<td>(first entry)</td>',
               '<td colspan=', ($nbcol-5), '></td></tr>';
      }
      mysql_free_result($result);

      echo "</table>\n";
   } //show_list


   $menu_array = array(
      T_('Refresh') => $page,
      T_('Show FAQ Log') => "admin_show_faqlog.php",
   );

   end_page(@$menu_array);
}//main


function show_preview( $level, $question, $answer, $reference, $id='preview', $rx_term='' )
{
   global $objtype;

   if ( $objtype == TXTOBJTYPE_INTRO ) // Intro-view, see also introduction.php
   {
      if ( $level == 1 )
         section('IntroPreview', $question );
      else
      {
         section('IntroPreview', T_('Preview') );
         echo "<dl><dt>$question</dt>\n<dd>$answer</dd></dl>\n";
      }
   }
   elseif ( $objtype == TXTOBJTYPE_LINKS ) // Links-view, see also links.php
   {
      if ( $level == 1 )
         section('LinksPreview', $question );
      else
      {
         add_link_page_link( $reference, $question, $answer );
         add_link_page_link();
      }
   }
   else // FAQ-view, see also faq.php
   {
      echo "<table class=FAQ><tr><td class=FAQread>\n";
      echo faq_item_html( 0);
      echo faq_item_html( $level, $question, $answer, ( $level == 1 ? "href=\"#$id\"" : "name=\"$id\"" ), '', $rx_term );
      echo faq_item_html(-1);
      echo "</td></tr></table>\n";
   }
}//show_preview

function check_url( $str )
{
   return (preg_match("/^[a-z0-9_\-:\/\.~\?%#@]+$/i", $str)) ? $str : '';
}

function check_reference( &$errors, $objtype, $reference )
{
   if ( $objtype == TXTOBJTYPE_LINKS )
   {
      if ( (string)$reference != '' )
      {
         if ( !check_url($reference) )
            $errors[] = 'Reference is no valid URL';
      }
      else
         $errors[] = 'Reference with URL is missing';
   }
}//check_reference

?>
