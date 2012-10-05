<?php
echo "<html><body bgcolor=white>\n";

echo "<p><b>APC Cache Info (System):</b><br>\n";
echo '<pre>', print_r(apc_cache_info(), true), "</pre>\n";

echo "<p><b>APC Cache Info (User):</b><br>\n";
echo '<pre>', print_r(apc_cache_info('user'), true), "</pre>\n";

echo "</body></html>\n";
?>
