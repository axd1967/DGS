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
use IO::Handle;
use Math::Round qw(round);


sub draw_filled_square
{
    my ($sz) = @_;
    $a = $size / $final_size * round (($final_size - $final_size * $sz) / 2);
    gimp_rect_select ($theImage, $a, $a, ($size - 2 * $a), ($size - 2 * $a), REPLACE, 0, 0);
    gimp_edit_fill ($theLayer, FG_IMAGE_FILL);
}

sub draw_square
{
    my ($sz, $thickness) = @_;
    $a1 = $size / $final_size * round (($final_size - $final_size * $sz) / 2);
    $a2 = ($a1 + round ($size * $thickness));
    gimp_rect_select ($theImage, $a1, $a1, ($size - 2 * $a1), ($size - 2 * $a1), REPLACE, 0, 0);
    gimp_rect_select ($theImage, $a2, $a2, ($size - 2 * $a2), ($size - 2 * $a2), SUB, 0, 0);
    gimp_edit_fill ($theLayer, FG_IMAGE_FILL);
    gimp_selection_none ($theImage);
}

sub draw_circle
{
    my ($sz, $thickness) = @_;
    $a1 = round (($size - $size * $sz) / 2);
    $a2 = ($a1 + round ($size * $thickness));
    gimp_ellipse_select ($theImage, $a1, $a1, ($size - 2 * $a1), ($size - 2 * $a1), REPLACE, 1, 0, 0);
    gimp_ellipse_select ($theImage, $a2, $a2, ($size - 2 * $a2), ($size - 2 * $a2), SUB, 1, 0, 0);
    gimp_edit_fill ($theLayer, FG_IMAGE_FILL);
    gimp_selection_none ($theImage);
}

sub select_line
{
    my ($p1x, $p1y, $p2x, $p2y, $thickness, $operation) = @_;
    $b0x = ($p1y - $p2y);
    $b0y = ($p2x - $p1x);
    $bl = 0.5 * $thickness * 1 / sqrt (($b0x * $b0x + $b0y * $b0y));
    $bx = $b0x * $bl;
    $by = $b0y * $bl;

    gimp_free_select ($theImage, 10,
                      [ $p1x + $bx, $p1y + $by, $p2x + $bx, $p2y + $by, $p2x - $bx,
                        $p2y - $by, $p1x - $bx, $p1y - $by, $p1x + $bx, $p1y + $by ],
                      $operation, 1, 0, 0);
}

sub draw_x_mark
{
    my ($sz, $thickness) = @_;
    $a = round (($size - $size * $sz) / 2);
    $th = $size * $thickness;
    select_line ($a, $a, ($size - $a), ($size - $a), $th, REPLACE);
    select_line ($a, ($size - $a), ($size - $a), $a, $th, ADD);
    gimp_edit_fill ($theLayer, FG_IMAGE_FILL);
    gimp_selection_none ($theImage);
}

sub draw_triangle
{
    my ($sz, $thickness) = @_;
    $v1 = 3.14159265 * 1 / 6;
    $v2 = 3.14159265 * 5 / 6;
    $v3 = 3.14159265 * -3 / 6;
    $th = $size * $thickness;
    $p1x = ($size / 2 + cos ($v1) * $size * $sz);
    $p1y = ($size / 2 + sin ($v1) * $size * $sz);
    $p2x = ($size / 2 + cos ($v2) * $size * $sz);
    $p2y = ($size / 2 + sin ($v2) * $size * $sz);
    $p3x = ($size / 2 + cos ($v3) * $size * $sz);
    $p3y = ($size / 2 + sin ($v3) * $size * $sz);
    $y = ($p1y + 0.5 * $th) * $final_size / $size;
    $dy = (round ($y) - $y) * $size / $final_size;
    $p1y = ($p1y + $dy);
    $p2y = ($p2y + $dy);
    $p3y = ($p3y + $dy);
    select_line ($p1x, $p1y, $p2x, $p2y, $th, REPLACE);
    select_line ($p2x, $p2y, $p3x, $p3y, $th, ADD);
    select_line ($p3x, $p3y, $p1x, $p1y, $th, ADD);
    gimp_edit_fill ($theLayer, FG_IMAGE_FILL);
    gimp_selection_none ($theImage);
}

sub get_font_height
{
    my ($filename) = @_;
    $im = gimp_file_load ($filename, $filename);
    $ly = gimp_text ($im, -1, 0, 0, "0123456789", 0, 1, $number_font_size, PIXELS, "*", $number_font, $number_font_weight, "r", "*", "*", "*", "*");
    $font_height = gimp_drawable_height ($ly);
    gimp_image_delete ($im);
    $font_height;
}

sub draw_number
{
    my ($text) = @_;
    $newLayer = gimp_text ($theImage, -1, 0, 0, $text, 0, 1, $number_font_size, PIXELS, "*", $number_font, $number_font_weight, "r", "*", "*", "*", "*");
    if (gimp_drawable_width ($newLayer) > 400)
    {
        gimp_layer_scale ($newLayer, 400, gimp_drawable_height ($newLayer), 0);
    }
    $w = gimp_drawable_width ($newLayer);
    if( int($text) % 10 == 1 )
    {
        $w +=  30;
    }
    gimp_layer_translate ($newLayer, (500 / 2 - $w / 2), (500 / 2 - $number_font_height / 2));
    $theLayer = gimp_image_merge_visible_layers ($theImage, CLIP_TO_BOTTOM_LAYER);
}

sub draw_board_lines
{
    my ($right, $up, $h, $clear) = @_;
    if ($clear == 1)
    {
        clear_image ();
    }
    gimp_palette_set_foreground ([0, 0, 0]);
    $c = ($final_size - $linewidth) / 2;
    $d = ($final_size + $linewidth) / 2;

    if ($right <= 0)
    {
        gimp_rect_select ($theImage, $c, $c, $d, $linewidth, ADD, 0, 0);
    }
    if ($up >= 0)
    {
        gimp_rect_select ($theImage, $c, 0, $linewidth, $d, ADD, 0, 0);
    }
    if ($right >= 0)
    {
        gimp_rect_select ($theImage, 0, $c, $d, $linewidth, ADD, 0, 0);
    }
    if ($up <= 0)
    {
        gimp_rect_select ($theImage, $c, $c, $linewidth, $d, ADD, 0, 0);
    }
    $hoshi_sz = (($d - $c) + 2 * $h);
    if ($h > 0)
    {
        gimp_ellipse_select ($theImage, ($c - $h), ($c - $h), $hoshi_sz, $hoshi_sz, ADD, 0, 0, 0);
    }
    gimp_edit_fill ($theLayer, FG_IMAGE_FILL);
}

sub draw_letter
{
    my ($text, $size_x, $size_y) = @_;
    $floating = gimp_text ($theImage, $theLayer, 0, 0, $text, 0, 1, $letter_font_size, PIXELS, "*", $letter_font, $letter_font_weight, "r", "*", "*", "*", "*");
    $w = gimp_drawable_width ($floating);
    $h = gimp_drawable_height ($floating);
    gimp_layer_translate ($floating, ($size_x - $w) / 2, (1 + ($size_y - $h - 1) / 2));
    gimp_floating_sel_anchor ($floating);
}

sub gifify
{
    $fg = gimp_palette_get_foreground ();
    gimp_palette_set_foreground ([237, 183, 123]);
    gimp_selection_none ($theImage);
    if ($have_alpha_background == 0)
    {
        $mask = gimp_layer_create_mask ($theLayer, ALPHA_MASK);
        gimp_image_add_layer_mask ($theImage, $theLayer, $mask);
        gimp_threshold ($mask, 50, 255);
    }
    $newLayer = gimp_layer_copy ($theLayer, 1);
    gimp_image_add_layer ($theImage, $newLayer, 1);
    gimp_drawable_fill ($newLayer, FG_IMAGE_FILL);
    $theLayer = gimp_image_merge_visible_layers ($theImage, 1);
    gimp_palette_set_foreground ($fg);
}

sub save_image
{
    my ($name, $delete) = @_;
    print "$name ";
    if( $size != $final_size )
    {
        gimp_image_scale( $theImage, $final_size, $final_size );
    }
    #file_png_save( $theImage, $theLayer, $final_size."/".$name.".orig.png", $name."orig.png", 0, 9, 0, 0, 0, 0, 0 );
    gifify ();
    gimp_convert_indexed ($theImage, 1, 0, 50, 0, 1, "");
    file_gif_save( $theImage, $theLayer, $final_size."/".$name.".gif", $name.".gif", 0, 0, 0, 0);
    gimp_convert_rgb( $theImage );
    if ($delete == 1)
    {
        gimp_image_delete( $theImage );
        $theImage = -1;
    }
}

sub clear_image
{
    $fg = gimp_palette_get_foreground ();
    gimp_palette_set_foreground ([0, 0, 0]);
    gimp_selection_none ($theImage);
    gimp_edit_fill ($theLayer, FG_IMAGE_FILL);
    gimp_edit_clear ($theLayer);
    gimp_palette_set_foreground ($fg);
}

sub bg_fill_image
{
    gimp_selection_none ($theImage);
    gimp_edit_fill ($theLayer, BG_IMAGE_FILL);
}

sub resize
{
    my ($new_size_x, $new_size_y) = @_;
    gimp_image_scale ($theImage, $new_size_x, $new_size_y);
    $size = $new_size_y;
}

sub load_image
{
    my ($filename, $scaled, $fg_color) = @_;
    $theImage = gimp_file_load ($filename, $filename);
    gimp_image_undo_disable ($theImage);
    if ($scaled == 1)
    {
        gimp_image_scale ($theImage, $final_size, $final_size);
        $size = $final_size;
    }
    else
    {
        $size = gimp_image_height ($theImage);
    }
    $theLayer = gimp_image_active_drawable ($theImage);
    gimp_palette_set_foreground ($fg_color);
    gimp_selection_none ($theImage);
}

sub new_image
{
    my ($height, $width, $fg_color) = @_;
    $theImage = gimp_image_new ($height, $width, RGB_IMAGE);
    $theLayer = gimp_layer_new ($theImage, $height, $width, RGBA_IMAGE, "", 100, NORMAL_MODE);
    $size = $height;
    gimp_image_add_layer ($theImage, $theLayer, 0);
    gimp_palette_set_foreground ($fg_color);
}






##########################################
#                                        #
#       Start generating images !!       #
#                                        #
##########################################


Gimp::init;

$number_font='helvetica';
$number_font_weight='bold';

$letter_font='newcenturyschlbk';
$letter_font_weight='medium';

@Sizes = grep { $_ > 0 } @ARGV;

if( $#Sizes < 1 )
{
    @Sizes = (13, 17, 21, 25, 29, 35, 42, 50);
}
#    @Sizes = (13, 15, 17, 19, 21, 25, 29, 35, 42, 50, 58, 70, 84, 100)


foreach $final_size (@Sizes)
{

    mkdir $final_size;

    $size = 0;
    $theImage = -1;
    $theLayer = -1;

    autoflush STDOUT 1;

    print "\n\n-----------------------------\n";
    print "       Size: $final_size\n";
    print "-----------------------------\n";


    if( $ARGV[0] ne 'board' )
    {
        $have_alpha_background = 1;
        for $color ('b', 'w')
        {
            if( $color eq 'b' )
            {
                $file='BigBlack.png';
                $markfile='BigBlackMark.png';
                $foreground_color='#ffffff';
            }
            else
            {
                $file='BigWhite.png';
                $markfile='BigWhiteMark.png';
                $foreground_color='#000000';
            }



#--------------- Draw normal stone -------------

            load_image ($file, 1, $foreground_color);
            save_image ($color, 1);


#--------------- Draw marked stones -------------

            load_image ($markfile, 1, $foreground_color);
            save_image ($color."m", 1);

            load_image ($file, 0, $foreground_color);
            draw_triangle (0.35, 0.04);
            save_image ($color."t", 1);

            load_image ($file, 0, $foreground_color);
            draw_square (0.52, 0.04);
            save_image ($color."s", 1);

            load_image ($file, 0, $foreground_color);
            draw_circle (0.58, 0.04);
            save_image ($color."c", 1);

            load_image ($file, 0, $foreground_color);
            draw_x_mark (0.45, 0.05);save_image ($color."x", 1);


            if( $color eq 'b' )
            {
                load_image ($file, 0, [255, 255, 255]);
                draw_filled_square (0.41);
                save_image ($color."w", 1);
            }
            else
            {
                load_image ($file, 0, [0, 0, 0]);
                draw_filled_square (0.41);
                save_image ($color."b", 1);
            }


#--------------- Draw numbered stones -------------

            $number_font_size =
                ( $final_size < 42 ?
                  5 * (70 * ($final_size - 13) + 80 * (35 - $final_size)) / (35 - 13) :
                  5 * 70 );

            $thickn = ( $final_size < 21 ?
                        (0.04 * ($final_size - 13) + 0.07 * (21 - $final_size)) / (21 - 13) :
                        0.04 );

            $number_font_height = get_font_height ($file);

            for($k=1; $k < 101; $k++)
            {
                load_image( $file, 0, $foreground_color );
                draw_number( $k );
                save_image( $color.$k, 1 );
            }
        }


#-------------- YinYang/play --------------

        load_image ("YinYang.png", 1, $foreground_color);
        save_image ("y", 1);

        load_image ("BigPlayBlack.png", 0, $foreground_color);
        resize (round ($final_size * 644 / 502), $final_size);
        save_image ("pb", 1);

        load_image ("BigPlayWhite.png", 0, $foreground_color);
        resize (round ($final_size * 644 / 502), $final_size);
        save_image ("pw", 1);
    }



    if( $ARGV[0] ne 'stones' )
    {

        $have_alpha_background = 0;

#--------------- Draw board lines -------------

        new_image( $final_size, $final_size, [0, 0, 0] );
        $linewidth = ($final_size > 40 ? 2 : 1);
        $upchars = ['u','e','d'];
        $rightchars = ['l','','r'];
        $hoshi = 0;

        for( $up=-1; $up < 2; $up++ )
        {
            $upchar = $upchars->[$up+1];

            for( $right=-1; $right < 2; $right++ )
            {
                $rightchar = $rightchars->[$right+1];

                draw_board_lines ($right, $up, $hoshi, 1);
                save_image ($upchar.$rightchar, 0);

                draw_board_lines ($right, $up, $hoshi, 1);
                draw_square (0.52, $thickn);
                save_image ($upchar.$rightchar."s", 0);

                clear_image ();
                resize (500, 500);
                draw_triangle (0.35, $thickn);
                resize ($final_size, $final_size);
                draw_board_lines ($right, $up, $hoshi, 0);
                save_image ($upchar.$rightchar."t", 0);

                clear_image ();
                resize (500, 500);
                draw_circle (0.58, $thickn);
                resize ($final_size, $final_size);
                draw_board_lines ($right, $up, $hoshi, 0);
                save_image ($upchar.$rightchar."c", 0);

                clear_image ();
                resize (500, 500);
                draw_x_mark (0.45, $thickn * 1.25);
                resize ($final_size, $final_size);
                draw_board_lines ($right, $up, $hoshi, 0);
                save_image ($upchar.$rightchar."x", 0);

                draw_board_lines ($right, $up, $hoshi, 1);
                gimp_palette_set_foreground ([0, 0, 0]);
                draw_filled_square (0.41);
                save_image ($upchar.$rightchar."b", 0);

                draw_board_lines ($right, $up, $hoshi, 1);
                gimp_palette_set_foreground ([255, 255, 255]);
                draw_filled_square (0.41);
                save_image ($upchar.$rightchar."w", 0);

                draw_board_lines ($right, $up, $hoshi, 1);
                gimp_palette_set_foreground ([248, 103, 80]);
                draw_filled_square (0.41);
                save_image ($upchar.$rightchar."d", 0);

                if( $right == 0 and $up == 0 )
                {
                    if( $upchar eq "e" )
                    {
                        $upchar = "h";
                        $hoshi = ($final_size >= 44 ? 3
                                  : $final_size >= 19 ? 2 : 1 );
                        $right --;
                    }
                    else
                    {
                        $upchar = "e";
                        $hoshi = 0;
                    }
                }
            }
        }


#--------------- Draw board letters -------------

        $letter_font_size = $final_size * 7 / 10;
        $letters = "abcdefghijklmnopqrstuvwxyz";
        gimp_palette_set_foreground ([0, 0, 0]);

        for($k=0; $k < 26; $k++)
        {
            $letter = substr($letters, $k, 1);
            clear_image ();
            draw_letter ("b    ".$letter."    q", $size, $size);
            save_image ("l".$letter, 0);
        }

#--------------- Draw redo/undo -------------

        gimp_palette_set_background ([253, 214, 155]);
        $letter_font_size = $final_size * 7 / 10;
        resize ($final_size * 2, $final_size);
        bg_fill_image ();
        draw_letter ("b  undo  q", $size * 2, $size);
        save_image ("undo", 0);

        bg_fill_image ();
        draw_letter ("b  redo  q", $size * 2, $size);
        save_image ("redo", 0);


#--------------- Draw coord images ---------------

        gimp_palette_set_background ([247, 245, 227]);
        $letter_font_size = $final_size * 3 / 5;
        resize ($final_size, $final_size);
        $letters = "abcdefghjklmnopqrstuvwxyz";

        for($k=0; $k < 25; $k++)
        {
            $letter = substr($letters, $k, 1);
            bg_fill_image ();
            draw_letter ("b    ".$letter."    q", $size, $size);
            save_image ("c".$letter, 0);
        }

        $size_x = $final_size * 31 / 25;
        resize ($size_x, $final_size);

        for($k=1; $k < 26; $k++)
        {
            bg_fill_image ();
            draw_letter ("0    ".$k."    0", $size_x, $size);
            save_image ("c".$k, 0);
        }
    }
}

print "\n\n Done! \n\n";
Gimp::end;
