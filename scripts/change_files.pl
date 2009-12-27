#!/usr/bin/perl

## Dragon Go Server
## Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar
##
## This program is free software: you can redistribute it and/or modify
## it under the terms of the GNU Affero General Public License as
## published by the Free Software Foundation, either version 3 of the
## License, or (at your option) any later version.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU Affero General Public License for more details.
##
## You should have received a copy of the GNU Affero General Public License
## along with this program.  If not, see <http://www.gnu.org/licenses/>.


# Usage: change_files.pl <file-list
#
# Reads all files, apply changes and save change back.
# Can be adjusted for change at hand.
#
# Current-task:
# - change preamble for license-change

use strict;

# ---------- init some vars --------------------------------

# old preamble for documentation-purpose:
my $OLD_PREAMBLE = <<'___END_PREAMBLE___';
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
___END_PREAMBLE___

# new preamble:
my $PREAMBLE = <<'___END_PREAMBLE___';
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
___END_PREAMBLE___

# ---------- process all files -----------------------------

# read filenames
my @files = ();
while (<>) {
   chomp;
   push @files, $_ unless /^\s*$/;
}

foreach my $file (@files) {
   # read file into buffer
   my $buf = '';
   open(IN, "<$file") or die "Can't open file [$file] for reading\n";
   while (<IN>) {
      $buf .= $_;
   }
   close IN;

   # apply change for file on $buf
   print STDERR "Processing file [$file] ...\n";
   #--------------------------------------------------------
   # replace old preamble with new one
   $buf =~ s/This program is free software.*?Suite 330, Boston, MA 02111-1307, USA\.\n/$PREAMBLE/s;
   #--------------------------------------------------------

   # write file back
   open(OUT, ">$file") or die "Can't open file [$file] for writing\n";
   print OUT $buf;
   close OUT;
}

exit 0;

__END__

# for documentation-purposes, here's the list of
# processed filenames for preamble-license-change:

./code_examples/filter_example.php
./code_examples/filter_example2.php
./code_examples/form_example.php
./code_examples/form_example2.php
./code_examples/query_sql.php
./code_examples/test_nigiri_random.php
./code_examples/tokenizer_example.php
./add_to_waitingroom.php
./admin.php
./admin_admins.php
./admin_do_translators.php
./admin_faq.php
./admin_password.php
./admin_translators.php
./change_bio.php
./change_password.php
./change_profile.php
./clock_tick.php
./confirm.php
./create_tournament.php
./daily_cron.php
./do_registration.php
./docs.php
./edit_bio.php
./edit_contact.php
./edit_folders.php
./edit_password.php
./edit_profile.php
./edit_vacation.php
./error.php
./faq.php
./forgot.php
./game.php
./game_comments.php
./halfhourly_cron.php
./index.php
./install.php
./introduction.php
./join_waitingroom_game.php
./licence.php
./links.php
./list_contacts.php
./list_messages.php
./list_tournaments.php
./login.php
./message.php
./message_selector.php
./new_tournament.php
./news.php
./opponents.php
./people.php
./quick_play.php
./quick_status.php
./ratinggraph.php
./ratingpng.php
./register.php
./search_messages.php
./send_message.php
./send_new_password.php
./sgf.php
./show_games.php
./show_tournament.php
./site_map.php
./snapshot.php
./statistics.php
./statisticspng.php
./statratingspng.php
./status.php
./todo.php
./translate.php
./update_translation.php
./userinfo.php
./users.php
./waiting_room.php
./forum/admin.php
./forum/forum_functions.php
./forum/index.php
./forum/list.php
./forum/post.php
./forum/read.php
./forum/search.php
./goodies/index.php
./include/tournamenttypes/all_types.php
./include/tournamenttypes/index.php
./include/tournamenttypes/macmahon.php
./include/GoDiagram.php
./include/board.php
./include/connect2mysql.php
./include/contacts.php
./include/coords.php
./include/countries.php
./include/error_functions.php
./include/faq_functions.php
./include/filter.php
./include/filter_functions.php
./include/filter_parser.php
./include/form_functions.php
./include/game_functions.php
./include/graph.php
./include/index.php
./include/make_game.php
./include/make_translationfiles.php
./include/message_functions.php
./include/move.php
./include/quick_common.php
./include/rating.php
./include/sgf_parser.php
./include/std_classes.php
./include/std_functions.php
./include/table_columns.php
./include/table_infos.php
./include/time_functions.php
./include/timezones.php
./include/tokenizer.php
./include/tournament.php
./include/tournament_round.php
./include/translation_functions.php
./js/goeditor.js
./js/index.php
./pattern/index.php
./pattern/make_handicap_pattern.php
./rss/index.php
./rss/status.php
./scripts/browser_stats.php
./scripts/convert_from_old_forum.php
./scripts/convert_posindex.php
./scripts/data_export.php
./scripts/data_report.php
./scripts/game_consistency.php
./scripts/generate_translation_texts.php
./scripts/images_makefile
./scripts/index.php
./scripts/mailtest.php
./scripts/make_all_translationfiles.php
./scripts/message_consistency.php
./scripts/phpinfo.php
./scripts/player_consistency.php
./scripts/recalculate_ratings2.php
./scripts/start_frozen_clocks.php
./scripts/translation_consistency.php
./scripts/update_translation_pages.php
./skins/dragon/index.php
./skins/dragon/print.css
./skins/dragon/screen.css
./skins/index.php
./skins/known_skins.php
./skins/dragon2/index.php
./skins/dragon2/screen.css
./wap/index.php
./wap/status.php

