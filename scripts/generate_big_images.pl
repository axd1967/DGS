#!/usr/bin/perl

# Dragon Go Server
# Copyright (C) 2003  Erik Ouchterlony

# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.

# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software Foundation,
# Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.

use Gimp qw(:auto);

Gimp::init;

$infile = $ARGV[1];
$outfile = $ARGV[0];

if( $outfile eq 'BigBlackMark.png' || $outfile eq 'BigWhiteMark.png' )
{
    if( $outfile eq 'BigBlackMark.png' )
    {
        $file='BigBlack.png';
        $fg_color="#ffffff";
    }
    else
    {
        $file='BigWhite.png';
        $fg_color="#000000";
    }

    $theImage = gimp_file_load($file, $file);
    $theLayer = gimp_image_active_drawable( $theImage );
    plug_in_autocrop( $theImage, $theLayer );
    gimp_palette_set_foreground( $fg_color );
    gimp_ellipse_select( $theImage, 100, 100, 300, 300, REPLACE, 1, 0, 0);
    gimp_ellipse_select( $theImage, 150, 150, 200, 200, SUB, 1, 0, 0);
    gimp_bucket_fill( $theLayer, FG_BUCKET_FILL, NORMAL_MODE, 80, 15, 1, 125, 125);
    gimp_selection_none( $theImage);
    file_png_save( $theImage, $theLayer, $outfile, $outfile, 0, 9, 0, 0, 0, 0, 0);
}
elsif( $outfile eq 'BigBlack.png' || $outfile eq 'BigWhite.png' ||
       $outfile eq 'BigPlayBlack.png' || $outfile eq 'BigPlayWhite.png' ||
       $outfile eq 'YinYang.png' )
{
    $theImage = gimp_file_load($infile, $infile);
    $theLayer = gimp_image_active_drawable( $theImage );
    plug_in_autocrop( $theImage, $theLayer );
    print $file . "\n";
    file_png_save( $theImage, $theLayer, $outfile, $outfile, 0, 9, 0, 0, 0, 0, 0);
}

print $outfile . "\n";

Gimp::end;

