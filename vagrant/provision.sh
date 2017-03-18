#!/usr/bin/env bash

set -x

cd /vagrant

source my.config

#BASE=/vagrant/server
#source $BASE/const.sh

SCRATCH=$(mktemp -d -t tmp.XXXXXXXXXX)

function finish {
    rm -rf "$SCRATCH"

    # and (re)start services here
    #sudo /etc/init.d/something start
    #service mysql restart
}
trap finish EXIT

apt-get update

# MySQL

apt-get install -y \
    debconf-utils \
    \

export DEBIAN_FRONTEND=noninteractive
debconf-set-selections <<< "mysql-server-5.5 mysql-server/root_password password "$MYSQL_ROOT_PASSWORD
debconf-set-selections <<< "mysql-server-5.5 mysql-server/root_password_again password "$MYSQL_ROOT_PASSWORD
apt-get install -y \
    mysql-server-5.5 \
    \

# Run the MySQL Secure Installation wizard
#mysql_secure_installation
#
#sed -i 's/127\.0\.0\.1/0\.0\.0\.0/g' /etc/mysql/my.cnf
#mysql -uroot -p -e 'USE mysql; UPDATE `user` SET `Host`="%" WHERE `User`="root" AND `Host`="localhost"; DELETE FROM `user` WHERE `Host` != "%" AND `User`="root"; FLUSH PRIVILEGES;'
#

apt-get install -y \
    php5 \
    apache2 \
    git \
    tree \
    \

# create users
#useradd --shell /bin/bash --create-home --home /home/$USER1 $USER1

# create directories
# ...