#!/bin/bash
#ioc2rpz installation/configuration script

SYSUSER=`whoami | awk '{print $1}'`
IO2_ROOT="/opt/ioc2rpz.gui"

####check if sqlite db exists io2cfg/io2db.sqlite
if [ ! -f ${IO2_ROOT}/www/io2cfg/io2db.sqlite ]; then
    php ${IO2_ROOT}/scripts/init_db.php
    chmod 660 ${IO2_ROOT}/www/io2cfg/io2db.sqlite
    chown apache:root ${IO2_ROOT}/www/io2cfg/io2db.sqlite
    chmod 775 ${IO2_ROOT}/www/io2cfg
    chown root:apache ${IO2_ROOT}/www/io2cfg
fi

####check if ssl certificates are installed
#/etc/apache2/ssl
#SSLCertificateFile /etc/ssl/apache2/server.pem
#SSLCertificateKeyFile /etc/ssl/apache2/server.key
#-rw------- 1 root root 1679 May  6 06:19 /etc/ssl/apache2/server.key


cat >> /tmp/$SYSUSER  << EOF
###Push updates
* * * * *  /usr/bin/php ${IO2_ROOT}/scripts/publish_cfg.php
* * * * *  sleep 10; /usr/bin/php ${IO2_ROOT}/scripts/publish_cfg.php
* * * * *  sleep 20; /usr/bin/php ${IO2_ROOT}/scripts/publish_cfg.php
* * * * *  sleep 30; /usr/bin/php ${IO2_ROOT}/scripts/publish_cfg.php
* * * * *  sleep 40; /usr/bin/php ${IO2_ROOT}/scripts/publish_cfg.php
* * * * *  sleep 50; /usr/bin/php ${IO2_ROOT}/scripts/publish_cfg.php

EOF
cat /tmp/$SYSUSER | crontab -u $SYSUSER -
rm -rf /tmp/$SYSUSER

sed -i -e "s%\(DocumentRoot\).*%\1 /opt/ioc2rpz.gui/www%" -e "s%^#\(.*mod_rewrite.so\).*%\1%"  /etc/apache2/httpd.conf; \
sed -i -e "s%\(DocumentRoot\).*%\1 /opt/ioc2rpz.gui/www%"  /etc/apache2/conf.d/ssl.conf; \
echo -e "<Directory /opt/ioc2rpz.gui/www/>\nOptions FollowSymLinks\nAllowOverride Indexes\nRequire all granted\nRewriteEngine on\nRewriteCond %{HTTPS} off\nRewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [L]\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule . /index.php [L]\n</Directory>\n"  >> /etc/apache2/httpd.conf

###start cron
crond
###start apache2
/usr/sbin/httpd -D FOREGROUND -f /etc/apache2/httpd.conf