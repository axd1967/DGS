#!/usr/bin/perl

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
   push @files, $_;
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

