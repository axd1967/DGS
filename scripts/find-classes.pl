#!/usr/bin/perl
#
# Usage: scripts/find-classes.pl ` find -type f -name "*.php" ` >classes.txt
#
#    show list of all PHP-classes in DGS-code
#    output-line-format: "prefix <TAB> classname <TAB> line-count <TAB> filename"

my $prefix = "CHECK\t";

foreach my $filename (@ARGV) {
  print STDERR "Handling <$filename> ...\n";

  if (open(IN, "<$filename")) {
    my $class = '';
    my $lcnt = 0;
    while (<IN>) {
      chomp;
      $lcnt++;
      if( /^class ([a-z0-9_]+)/i ) {
         $class = $1;
      }
      elsif( $class && /function $class\b/i ) {
         print "$prefix$class\t$lcnt\t$filename\n";
         $class = '';
      }
    }
    close IN;
  }
}

exit 0;

