#!/bin/sh
scripts/find_translations.pl | php -q -C scripts/update_translation_info.php include/translation_info.php
