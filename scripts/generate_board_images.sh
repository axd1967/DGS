#!/bin/sh

# Dragon Go Server
# Copyright (C) 2002  Erik Ouchterlony

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


echo -e "\n\n\n\n  Generating a lot of images. This will take me a while...\n\n\n\n";

echo -e "\n\n------------------------------------------------------------------------------------\n";
echo -e "   First run povray (v3.5 or higher) to generate a couple of nice stone images.";
echo -e "\n------------------------------------------------------------------------------------\n";


povray +D +sp16 +ep4 -P +A +J +W1024 +H768 +FN +OBigBlack.png stone.pov
povray +D +sp16 +ep4 -P +A +J +W1024 +H768 +FN Declare=WHITE=1 +OBigWhite.png stone.pov
povray +D +sp16 +ep4 -P +A +J +W1024 +H768 +FN Declare=YINYANG=1 +OYinYang.png stone.pov

echo -e "\n\n-----------------------------------------------------------------------------\n";
echo -e "   Now use gimp (v1.2 or higher) to make all images used for the go board.";
echo -e "\n-----------------------------------------------------------------------------\n";


sizes="13 17 21 25 29 35 42 50";

colors="b w";


for color in $colors; do

if [[ "$color" == "b" ]]; then
    file="\"BigBlack.png\"";
    markfile="\"BigBlackMark.png\"";
    fg_color="'(255 255 255)";
else
    file="\"BigWhite.png\"";
    markfile="\"BigWhiteMark.png\"";
    fg_color="'(0 0 0)";
fi

#"
gimp -i -d -b "(begin
(set! theImage (car (gimp-file-load FALSE $file $file)))
(set! theLayer (car (gimp-image-active-drawable theImage)))
(plug-in-autocrop 1 theImage theLayer)
(gimp-palette-set-foreground $fg_color )
(gimp-message $file)
(file-png-save 1 theImage theLayer $file $file 0 9 0 0 0 0 0)
(gimp-ellipse-select theImage 100 100 300 300 REPLACE 1 0 0)
(gimp-ellipse-select theImage 150 150 200 200 SUB 1 0 0)
(gimp-bucket-fill theLayer FG-BUCKET-FILL NORMAL 80 15 1 125 125)
(gimp-selection-none theImage)
(gimp-message $markfile)
(file-png-save 1 theImage theLayer $markfile $markfile 0 9 0 0 0 0 0)
(gimp-quit 0))" -b "(gimp-quit 0)";
#"
done;

file="\"YinYang.png\"";

#"
gimp -i -d -b "(begin
(set! theImage (car (gimp-file-load FALSE $file $file)))
(set! theLayer (car (gimp-image-active-drawable theImage)))
(plug-in-autocrop 1 theImage theLayer)
(gimp-palette-set-foreground $fg_color )
(gimp-message $file)
(file-png-save 1 theImage theLayer $file $file 0 9 0 0 0 0 0)
(gimp-quit 0))" -b "(gimp-quit 0)";
#"

number_font="\"helvetica\"";
number_font_weight="\"bold\"";

letter_font="\"newcenturyschlbk\"";
letter_font_weight="\"medium\"";


for size in $sizes; do

for color in $colors; do

if [[ "$color" == "b" ]]; then
    file="\"BigBlack.png\"";
    markfile="\"BigBlackMark.png\"";
    fg_color="'(255 255 255)";
    prefix="\"b\"";
else
    file="\"BigWhite.png\"";
    markfile="\"BigWhiteMark.png\"";
    fg_color="'(0 0 0)";
    prefix="\"w\"";
fi

mkdir -p $size

#"
gimp -i -d -b "(begin

(define (div x y) (/ (- x (fmod x y)) y) )
(define (floor x) (- x (fmod x 1)))
(define (ceil x) (+ x (- 1 (fmod x 1))))
(define (round x) (floor (+ x 0.5)))

(define (draw-filled-square sz)
  (set! a (round (/ (- size (* size sz)) 2)))
  (gimp-rect-select theImage a a (- size (* 2 a)) (- size (* 2 a)) REPLACE 0 0)
  (gimp-edit-fill theLayer FG-IMAGE-FILL))

(define (draw-square sz thickness)
  (set! a1 (* (/ size final_size) (round (/ (- final_size (* final_size sz)) 2))))
  (set! a2 (+ a1 (round (* size thickness))))

  (gimp-rect-select theImage a1 a1 (- size (* 2 a1)) (- size (* 2 a1)) REPLACE 0 0)
  (gimp-rect-select theImage a2 a2 (- size (* 2 a2)) (- size (* 2 a2)) SUB 0 0)
  (gimp-edit-fill theLayer FG-IMAGE-FILL)
  (gimp-selection-none theImage))

(define (draw-circle sz thickness)
  (set! a1 (round (/ (- size (* size sz)) 2)))
  (set! a2 (+ a1 (round (* size thickness))))

  (gimp-ellipse-select theImage a1 a1 (- size (* 2 a1)) (- size (* 2 a1)) REPLACE 1 0 0)
  (gimp-ellipse-select theImage a2 a2 (- size (* 2 a2)) (- size (* 2 a2)) SUB 1 0 0)
  (gimp-edit-fill theLayer FG-IMAGE-FILL)
  (gimp-selection-none theImage))

(define (select-line p1x p1y p2x p2y thickness operation)
  (set! b0x (- p1y p2y))
  (set! b0y (- p2x p1x))
  (set! bl (* 0.5 thickness (/ 1 (sqrt (+ (* b0x b0x) (* b0y b0y))))))
  (set! bx (* b0x bl))
  (set! by (* b0y bl))

  (set! array (cons-array 10 'double))
  (aset array 0 (+ p1x bx))
  (aset array 1 (+ p1y by))
  (aset array 2 (+ p2x bx))
  (aset array 3 (+ p2y by))
  (aset array 4 (- p2x bx))
  (aset array 5 (- p2y by))
  (aset array 6 (- p1x bx))
  (aset array 7 (- p1y by))
  (aset array 8 (+ p1x bx))
  (aset array 9 (+ p1y by))
  (gimp-free-select theImage 10 array operation 1 0 0))

(define (draw-x-mark sz thickness)
  (set! a (round (/ (- size (* size sz)) 2)))
  (set! th (* size thickness))
  (select-line a a (- size a) (- size a) th REPLACE)
  (select-line a (- size a) (- size a) a th ADD)
  (gimp-edit-fill theLayer FG-IMAGE-FILL)
  (gimp-selection-none theImage))

(define (draw-triangle sz thickness)
  (set! v1 (* *pi* (/ 1 6)))
  (set! v2 (* *pi* (/ 5 6)))
  (set! v3 (* *pi* (/ -3 6)))
  (set! th (* size thickness))
  (set! p1x  (+ (/ size 2) (* (cos v1) size sz)))
  (set! p1y  (+ (/ size 2) (* (sin v1) size sz)))
  (set! p2x  (+ (/ size 2) (* (cos v2) size sz)))
  (set! p2y  (+ (/ size 2) (* (sin v2) size sz)))
  (set! p3x  (+ (/ size 2) (* (cos v3) size sz)))
  (set! p3y  (+ (/ size 2) (* (sin v3) size sz)))

  (set! y (* (+ p1y (* 0.5 th)) (/ final_size size)))
  (set! dy (* (- (round y) y) (/ size final_size)))
  (set! p1y (+ p1y dy))
  (set! p2y (+ p2y dy))
  (set! p3y (+ p3y dy))

  (select-line p1x p1y p2x p2y th REPLACE)
  (select-line p2x p2y p3x p3y th ADD)
  (select-line p3x p3y p1x p1y th ADD)

  (gimp-edit-fill theLayer FG-IMAGE-FILL)
  (gimp-selection-none theImage))

(define (get-font-height filename)
  (set! im (car (gimp-file-load FALSE filename filename)))
  (set! ly (car (gimp-text im -1 0 0 \"0123456789\" 0 TRUE number_font_size PIXELS \"*\" number_font number_font_weight \"r\" \"*\" \"*\" \"*\" \"*\")))
  (set! font_height (car (gimp-drawable-height ly)))
  (gimp-image-delete im)
  font_height)


(define (draw-number text)
  (set! newLayer (car (gimp-text theImage -1 0 0 text 0 1 number_font_size PIXELS \"*\" number_font number_font_weight \"r\" \"*\" \"*\" \"*\" \"*\")))

  (if (> (car (gimp-drawable-width newLayer)) 400)
      (gimp-layer-scale newLayer 400 (car (gimp-drawable-height newLayer)) 0))

  (set! w (car (gimp-drawable-width newLayer)))

  (if (= (fmod (parse-number text) 10) 1)
      (set! w (+ w 30)))

  (gimp-layer-translate newLayer (- (/ 500 2) (/ w 2))
                        (- (/ 500 2) (/ number_font_height 2)))

  (set! theLayer (car (gimp-image-merge-visible-layers theImage CLIP-TO-BOTTOM-LAYER)))
  )


(define (draw-board-lines right up h clear)
  (if (= clear 1) (clear-image))
  (gimp-palette-set-foreground '(0 0 0))
  (set! c (/ (- final_size linewidth) 2))
  (set! d (/ (+ final_size linewidth) 2))
  (set! len (/ (+ final_size linewidth) 2))

  (if (<= right 0) (gimp-rect-select theImage c c d linewidth ADD 0 0))
  (if (>= up 0) (gimp-rect-select theImage c 0 linewidth d ADD 0 0))

  (if (>= right 0) (gimp-rect-select theImage 0 c d linewidth ADD 0 0))
  (if (<= up 0) (gimp-rect-select theImage c c linewidth d ADD 0 0))

  (set! hoshi_sz (+ (- d c) (* 2 h)))
  (if (> h 0) (gimp-ellipse-select theImage (- c h) (- c h) hoshi_sz hoshi_sz ADD 0 0 0))

  (gimp-edit-fill theLayer FG-IMAGE-FILL)
  )

(define (draw-letter text size_x size_y)
  (set! floating (car (gimp-text theImage theLayer 0 0 text 0 1 letter_font_size PIXELS
                                 \"*\" letter_font letter_font_weight \"r\" \"*\"
                                 \"*\" \"*\" \"*\")))

  (set! w (car (gimp-drawable-width floating)))
  (set! h (car (gimp-drawable-height floating)))

  (gimp-layer-translate floating (/ (- size_x w) 2) (+ 1 (/ (- size_y h 1) 2)) )
  (gimp-floating-sel-anchor floating))

(define (gifify)
  (set! fg (car (gimp-palette-get-foreground)))
  (gimp-palette-set-foreground '(237 183 123))
  (gimp-selection-none theImage)
  (set! mask (car (gimp-layer-create-mask theLayer ALPHA-MASK)))
  (gimp-image-add-layer-mask theImage theLayer mask)
  (gimp-threshold mask 50 255)
  (set! newLayer (car (gimp-layer-copy theLayer 1)))
  (gimp-image-add-layer theImage newLayer 1)
  (gimp-drawable-fill newLayer FG-IMAGE-FILL)
  (set! theLayer (car (gimp-image-merge-visible-layers theImage 1)))
  (gimp-palette-set-foreground fg))

(define (save-image name delete)
  (gimp-message (string-append (number->string final_size) \"/\" name))
  (if (= size final_size) ()
      (gimp-image-scale theImage final_size final_size 0 0))
  (file-png-save 1 theImage theLayer
                 (string-append (number->string final_size) \"/\" name \".orig.png\")
                 (string-append name \"orig.png\") 0 9 0 0 0 0 0)

  (gifify)
  (gimp-convert-indexed theImage 1 0 50 0 1 \"\")
  (file-gif-save 1 theImage theLayer
                 (string-append (number->string final_size) \"/\" name \".gif\")
                 (string-append name \".gif\") 0 0 0 0)
  (gimp-convert-rgb theImage)

  (if (= delete 1) (begin (gimp-image-delete theImage) (set! theImage -1)))
  )

(define (clear-image)
  (set! fg (car (gimp-palette-get-foreground)))
  (gimp-palette-set-foreground '(0 0 0))
  (gimp-selection-none theImage)
  (gimp-edit-fill theLayer FG-IMAGE-FILL)
  (gimp-edit-clear theLayer)
  (gimp-palette-set-foreground fg))

(define (bg-fill-image)
  (gimp-selection-none theImage)
  (gimp-edit-fill theLayer BG-IMAGE-FILL))

(define (resize new_size_x new_size_y)
  (gimp-image-scale theImage new_size_x new_size_y)
  (set! size new_size_y))

(define (load-image filename scaled fg_color)
  (set! theImage (car (gimp-file-load 0 filename filename)))
  (gimp-image-undo-disable theImage)

  (if (= scaled 1)
      (begin (gimp-image-scale theImage final_size final_size 0 0)
             (set! size final_size))
      (set! size (car (gimp-image-height theImage))))

  (set! theLayer (car (gimp-image-active-drawable theImage)))
  (gimp-palette-set-foreground fg_color)
  (gimp-selection-none theImage))

(define (new-image height width fg_color)
  (set! theImage (car (gimp-image-new height width RGB)))
  (set! theLayer (car (gimp-layer-new theImage height width RGBA_IMAGE \"\" 100 NORMAL) ))
  (set! size height)
  (gimp-image-add-layer theImage theLayer 0)
  (gimp-palette-set-foreground fg_color))



; --------------------  Start  -----------------------

(set! final_size $size)
(set! file $file)
(set! markfile $markfile)
(set! foreground_color $fg_color)
(set! prefix $prefix)

(set! number_font $number_font)
(set! number_font_weight $number_font_weight)

(set! letter_font $letter_font)
(set! letter_font_weight $letter_font_weight)

(set! size 0)
(set! theImage -1)
(set! theLayer -1)

(gimp-message-set-handler 1)
(gimp-message \"-----------------------------\")
(gimp-message (string-append \"       Size: \" (number->string final_size) ))
(gimp-message (string-append \"       File: \" file ))
(gimp-message \"-----------------------------\")




;--------------- Draw normal stone -------------

(load-image file 1 foreground_color)
(save-image prefix 1)


;--------------- Draw marked stones -------------

(load-image markfile 1 foreground_color)
(save-image (string-append prefix \"m\") 1)

(load-image file 0 foreground_color)
(draw-triangle 0.35 0.04)
(save-image (string-append prefix \"t\") 1)

(load-image file 0 foreground_color)
(draw-square 0.52 0.04)
(save-image (string-append prefix \"s\") 1)

(load-image file 0 foreground_color)
(draw-circle 0.58 0.04)
(save-image (string-append prefix \"c\") 1)

(load-image file 0 foreground_color)
(draw-x-mark 0.45 0.05)
(save-image (string-append prefix \"x\") 1)

(if (= (strcmp prefix \"b\") 0)
    (begin
      (load-image file 0 '(255 255 255))
      (draw-filled-square 0.41)
      (save-image (string-append prefix \"w\") 1))
    (begin
      (load-image file 0 '(0 0 0))
      (draw-filled-square 0.41)
      (save-image (string-append prefix \"b\") 1)))


;--------------- Draw numbered stones -------------

(if (< final_size 42)
    (set! number_font_size (/ (* 5 (+ (* 70 (- final_size 13))
                                      (* 80 (- 35 final_size)))) (- 35 13)))
    (set! number_font_size (* 5 70)))

(if (< final_size 21)
    (set! thickn (/ (+ (* 0.04 (- final_size 13)) (* 0.07 (- 21 final_size))) (- 21 13)))
    (set! thickn 0.04))

(set! number_font_height (get-font-height file))

(set! k 1)
(while (< k 101)
       (load-image file 0 foreground_color)
       (draw-number (string-append (number->string k)))
       (save-image (string-append prefix (number->string k)) 1)
       (set! k (+ k 1)))


(if (= (strcmp prefix \"b\") 0)
    (begin

;-------------- YinYang --------------
      (load-image \"YinYang.png\" 1 foreground_color)
      (save-image \"y\" 1)

;--------------- Draw board lines -------------

      (new-image final_size final_size '(0 0 0))
      (set! linewidth (if (> final_size 40) 2 1))

      (set! up -1)
      (set! upchar \"u\")
      (set! hoshi 0)
      (while (< up 2)
             (set! right -1)
             (set! rightchar \"l\")
             (while (< right 2)
                    (draw-board-lines right up hoshi 1)
                    (save-image (string-append upchar rightchar) 0)

                    (draw-board-lines right up hoshi 1)
                    (draw-square 0.52 thickn)
                    (save-image (string-append upchar rightchar \"s\") 0)

                    (clear-image)
                    (resize 500 500)
                    (draw-triangle 0.35 thickn)
                    (resize final_size final_size)
                    (draw-board-lines right up hoshi 0)
                    (save-image (string-append upchar rightchar \"t\") 0)

                    (clear-image)
                    (resize 500 500)
                    (draw-circle 0.58 thickn)
                    (resize final_size final_size)
                    (draw-board-lines right up hoshi 0)
                    (save-image (string-append upchar rightchar \"c\") 0)

                    (clear-image)
                    (resize 500 500)
                    (draw-x-mark 0.45 (* thickn 1.25))
                    (resize final_size final_size)
                    (draw-board-lines right up hoshi 0)
                    (save-image (string-append upchar rightchar \"x\") 0)

                    (draw-board-lines right up hoshi 1)
                    (gimp-palette-set-foreground '(0 0 0))
                    (draw-filled-square 0.41)
                    (save-image (string-append upchar rightchar \"b\") 0)

                    (draw-board-lines right up hoshi 1)
                    (gimp-palette-set-foreground '(255 255 255))
                    (draw-filled-square 0.41)
                    (save-image (string-append upchar rightchar \"w\") 0)

                    (draw-board-lines right up hoshi 1)
                    (gimp-palette-set-foreground '(248 103 80))
                    (draw-filled-square 0.41)
                    (save-image (string-append upchar rightchar \"d\") 0)

                    (if (and (= 0 right) (= 0 up))
                        (if (= (strcmp upchar \"e\") 0)
                            (begin (set! upchar \"h\")
                                   (set! hoshi (cond ((>= final_size 44) 3)
                                                     ((>= final_size 19) 2) (1 1))))
                            (begin (set! upchar \"e\") (set! right 1) (set! hoshi 0)))
                        (set! right (+ 1 right)))

                    (set! rightchar (if (= right 0) \"\" \"r\"))
                    )

             (set! up (+ 1 up))
             (set! upchar (if (= up 0) \"e\" \"d\")))

;--------------- Draw board letters -------------

      (set! letter_font_size (/ (* final_size 7) 10))
      (set! k 1)
      (set! letters \"abcdefghijklmnopqrstuvwxyz\")
      (gimp-palette-set-foreground '(0 0 0))
      (while (< k 27)
             (set! letter (substring letters (- k 1) k))
             (clear-image)
             (draw-letter (string-append \"b    \" letter \"    q\") size size)
             (save-image (string-append \"l\" letter) 0)
             (set! k (+ k 1)))


;--------------- Draw coord images ---------------

      (gimp-palette-set-background '(247 245 227))
      (set! letter_font_size (/ (* final_size 3) 5))

      (set! k 1)
      (set! letters \"abcdefghjklmnopqrstuvwxyz\")
      (while (< k 26)
             (set! letter (substring letters (- k 1) k))
             (bg-fill-image)
             (draw-letter (string-append \"b    \" letter \"    q\") size size)
             (save-image (string-append \"c\" letter) 0)
             (set! k (+ k 1)))


      (set! size_x (/ (* final_size 31) 25))
      (resize size_x final_size)

      (set! k 1)
      (while (< k 26)
             (bg-fill-image)
             (draw-letter (string-append \"0    \" (number->string k) \"    0\")
                          size_x size)
             (save-image (string-append \"c\" (number->string k)) 0)
             (set! k (+ k 1)))
      ))

(gimp-quit 0)
)" -b "(gimp-quit 0)";
#"

done;

done;


echo -e "\n\n-----------------------------------------------------------------------------\n";
echo -e "   Finally use pngquant to compress the generated png images.";
echo -e "\n-----------------------------------------------------------------------------\n";

for size in $sizes; do
echo "  Size: $size";
cd $size;

rm -f *or8* ?.png ??.png ???.png ????.png

pngquant -force -ordered 50 [bwy]*.orig.png
pngquant -force -ordered 16 [cl]*.orig.png
pngquant -force -ordered 2 [eduh]*.orig.png
pngquant -force -ordered 16 [eduh]*[tcsxwbd].orig.png

rename 's/.orig-or8.png/.png/' *or8*

cd ..;

done;

echo -e "\n\n-----------------------------------------------------------------------------\n";
echo -e "   Finished!!!";
echo -e "\n-----------------------------------------------------------------------------\n";
