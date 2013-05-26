#!/usr/bin/perl
#
# Usage: scripts/find-classes.pl ` find -type f -name "*.php" ` >classes.txt
#
#    show list of all PHP-classes in DGS-code
#    output-line-format: "prefix <TAB> classname <TAB> line-count <TAB> STATIC|OBJ <TAB> filename"

my $prefix = "CHECK\t";

foreach my $filename (@ARGV) {
  print STDERR "Handling <$filename> ...\n";

  if (open(IN, "<$filename")) {
    my $class = '';
    my $class_line = 0;
    my $lcnt = 0;
    while (<IN>) {
      chomp;
      $lcnt++;
      if ( /^class ([a-z0-9_]+)/i ) {
         print "$prefix$class\t$class_line\tSTATIC\t$filename\n" if $class;
         $class = $1;
         $class_line = $lcnt;
      }
      elsif( $class && /function $class\b/i ) {
         print "$prefix$class\t$lcnt\tOBJ\t$filename\n";
         $class = '';
      }
    }
    print "$prefix$class\t$class_line\tSTATIC\t$filename\n" if $class;
    close IN;
  }

}

exit 0;

