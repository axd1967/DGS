#!/usr/bin/env bash

# TODO :turn this file into an install script that can be used on a real server

#set -x
set -e # exit on any error!

cd /vagrant
source my.config

LOCAL_CONF=$DGS_ROOT/include/config-local.php
if [ ! -f $LOCAL_CONF ]
then
    echo "please re-read the instructions concerning $LOCAL_CONF in my.config"
    exit 1
fi

# clone from vagrant home.
# this is done because symlinking (e.g. images) doesn't always work
#cd $DGS_ROOT
#git init .
#git clone $GIT_REPO/.git

# this script implements instructions from INSTALL

# images
# because symlinks don't always work in VMs, we just copy them
cd $DGS_ROOT
cp images/favicon.ico .
#cp /images/apple-touch-icon-*.png

#MYSQL_VERBOSE='--verbose'
MYSQL_VERBOSE=''

mysql --host=$MYSQLHOST --user=$MYSQL_DBA --password="$MYSQL_DBA_PASSWORD" --show-warnings $MYSQL_VERBOSE << HERE

DROP DATABASE IF EXISTS $DB_NAME;
CREATE DATABASE $DB_NAME;

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE, ALTER, DROP
          ON $DB_NAME.*
          TO $DRAGON_DB_ADMIN@localhost
          IDENTIFIED BY '$DRAGON_DB_ADMIN_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES
          ON $DB_NAME.*
          TO $MYSQLUSER@localhost
          IDENTIFIED BY '$MYSQLPASSWORD';

HERE

mysql --host=$MYSQLHOST --user=$DRAGON_DB_ADMIN --password="$DRAGON_DB_ADMIN_PASSWORD" --show-warnings $MYSQL_VERBOSE -D $DB_NAME < specs/db/dragon-ddl.sql
mysql --host=$MYSQLHOST --user=$DRAGON_DB_ADMIN --password="$DRAGON_DB_ADMIN_PASSWORD" --show-warnings $MYSQL_VERBOSE -D $DB_NAME < specs/db/dragon-data.sql

WWW_DATA_GRP="www-data"

#VERBOSE_MKDIR='--verbose'
VERBOSE_MKDIR=''

rm -rf translations
mkdir $VERBOSE translations
chgrp $WWW_DATA_GRP translations
chmod 775 translations/
pushd scripts
php make_all_translationfiles.php
popd

# TODO get latest translations

# TODO define admin

chgrp $WWW_DATA_GRP $LOCAL_CONF
chmod 640 $LOCAL_CONF

rm -rf $CACHE_FOLDER
mkdir $VERBOSE --parents $CACHE_FOLDER
chmod 775 $CACHE_FOLDER
chgrp $WWW_DATA_GRP $CACHE_FOLDER

mkdir $VERBOSE --parents $USERPIC
chgrp $WWW_DATA_GRP $USERPIC
chmod g+ws $USERPIC

mkdir $VERBOSE --parents $DATASTORE_FOLDER
mkdir $VERBOSE --parents $DATASTORE_FOLDER/rss
mkdir $VERBOSE --parents $DATASTORE_FOLDER/qst data-store/wap
chmod -R 775 $DATASTORE_FOLDER
chgrp -R $WWW_DATA_GRP $DATASTORE_FOLDER

# WEB SERVER
cd /vagrant

#cp info.php /var/www/html

#cp apache-dgs.conf /etc/apache2/sites-available/dgs.conf
#ln -sf /etc/apache2/sites-available/dgs.conf /etc/apache2/sites-enabled
#a2ensite dgs
#a2dissite 000-default.conf

service apache2 restart
