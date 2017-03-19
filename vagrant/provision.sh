#!/usr/bin/env bash

set -x

cd /vagrant

source my.config

apt-get update

# MySQL

apt-get install -y \
    debconf-utils \
    \

export DEBIAN_FRONTEND=noninteractive
debconf-set-selections <<< "mysql-server-5.5 mysql-server/root_password password "$MYSQL_DBA_PASSWORD
debconf-set-selections <<< "mysql-server-5.5 mysql-server/root_password_again password "$MYSQL_DBA_PASSWORD
apt-get install -y \
    mysql-server-5.5 \
    php5-mysql \
    php5 \
    apache2 \
    \

# Run the MySQL Secure Installation wizard
#mysql_secure_installation
#
#sed -i 's/127\.0\.0\.1/0\.0\.0\.0/g' /etc/mysql/my.cnf
#mysql -uroot -p -e 'USE mysql; UPDATE `user` SET `Host`="%" WHERE `User`="root" AND `Host`="localhost"; DELETE FROM `user` WHERE `Host` != "%" AND `User`="root"; FLUSH PRIVILEGES;'
#

apt-get install -y \
    git \
    tree \
    \

# create users
#useradd --shell /bin/bash --create-home --home /home/$DGS_USER $DGS_USER

# create directories
# ...

./install.sh
