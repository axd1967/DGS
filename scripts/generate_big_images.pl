#!/usr/bin/perl

# Dragon Go Server
# Copyright (C) 2001-2007  Erik Ouchterlony

# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.

# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.

# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

use Gimp qw(:auto);

Gimp::init;

$infile = $ARGV[1];
$outfile = $ARGV[0];

if( $outfile eq 'BigBlackMark.png' || $outfile eq 'BigWhiteMark.png' )
{
    if( $outfile eq 'BigBlackMark.png' )
    {
        $file='BigBlack.png';
        $fg_color= [255, 255, 255];
    }
    else
    {
        $file='BigWhite.png';
        $fg_color= [0, 0, 0];
    }

    $theImage = gimp_file_load($file, $file);
    $theLayer = gimp_image_active_drawable( $theImage );
    plug_in_autocrop( $theImage, $theLayer );
    gimp_palette_set_foreground( $fg_color );
    gimp_ellipse_select( $theImage, 100, 100, 300, 300, CHANNEL_OP_REPLACE, 1, 0, 0);
    gimp_ellipse_select( $theImage, 150, 150, 200, 200, CHANNEL_OP_SUBTRACT, 1, 0, 0);
    gimp_bucket_fill( $theLayer, FG_BUCKET_FILL, NORMAL_MODE, 80, 15, 1, 125, 125);
    gimp_selection_none( $theImage);
    file_png_save( $theImage, $theLayer, $outfile, $outfile, 0, 9, 0, 0, 0, 0, 0);
    print $outfile . "\n";
}
elsif( $outfile eq 'BigBlack.png' || $outfile eq 'BigWhite.png' ||
       $outfile eq 'BigPlayBlack.png' || $outfile eq 'BigPlayWhite.png' ||
       $outfile eq 'BigBlackBlack.png' || $outfile eq 'BigWhiteWhite.png' ||
       $outfile eq 'BigWhiteBlack.png' || $outfile eq 'BigBlackWhite.png' ||
       $outfile eq 'YinYang.png' )
{
    $theImage = gimp_file_load($infile, $infile);
    $theLayer = gimp_image_active_drawable( $theImage );
    plug_in_autocrop( $theImage, $theLayer );
    file_png_save( $theImage, $theLayer, $outfile, $outfile, 0, 9, 0, 0, 0, 0, 0);
    print $outfile . "\n";
}
else
{
    print "Error: Unknown file\n";
}

Gimp::end;

