# download webserver-logs from 'http://www.dragongoserver.net/scripts/backup/logs/'
#   files matching 'www.dragongoserver.net-combined_log*'

# prepare
mv www.dragongoserver.net-combined_log www.dragongoserver.net-combined_log-2012-05-17
gzip www.dragongoserver.net-combined_log-2012-05-17
zcat www.dragongoserver.net-combined_log-2012-05-17.gz |./parse-apache-logs.pl |gzip >log-2012-05-17.gz

zgrep -e '\.php' log-2012-05-17.gz | gzip >php_log-2012-05-17.gz
zgrep -ve '\.php' log-2012-05-17.gz | gzip >nonphp_log-2012-05-17.gz

# compile Java-analyzers
javac Calculate.java
javac CountUrls.java

# --------------------------------
# analyze prepared web-logs

# number of entries (all, dynamic, static)
zcat log-2012-05-17.gz | wc -l
zcat php_log-2012-05-17.gz | wc -l
zcat nonphp_log-2012-05-17.gz | wc -l

# static
zcat nonphp_log-2012-05-17.gz  |java Calculate  | tee result-static-2012-05-18
zcat nonphp_log-2012-05-17.gz  |./filter-rtime.pl 10000000 |java CountUrls | tee -a result-static-2012-05-18
zcat nonphp_log-2012-05-17.gz |cut -d' ' -f4 |sort |uniq -c |sort -rn | tee -a result-static-2012-05-18

# dynamic
zcat php_log-2012-05-17.gz |cut -d' ' -f4 |sort |uniq -c |sort -rn | tee result-dynamic-2012-05-18
zcat php_log-2012-05-17.gz |java Calculate | tee -a result-dynamic-2012-05-18
zcat php_log-2012-05-17.gz |./filter-rtime.pl 10000000 | java CountUrls | tee -a result-dynamic-2012-05-18
zcat php_log-2012-05-17.gz |./filter-rtime.pl 5000000 10000000 | java CountUrls | tee -a result-dynamic-2012-05-18
zcat php_log-2012-05-17.gz |./filter-rtime.pl 15000000 | java CountUrls | tee -a result-dynamic-2012-05-18

zcat php_log-2012-05-17.gz |./filter.pl 4 200 | java CountUrls |tee -a result-dynamic-2012-05-18

zcat php_log-2012-05-17.gz | java CountUrls | tee -a result-dynamic-2012-05-18

# all
zcat log-2012-05-17.gz | java CountUrls | tee -a result-dynamic-2012-05-18

