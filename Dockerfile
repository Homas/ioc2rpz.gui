#Copyright 2017-2018 Vadim Pavlov pvm(dot)del[at]gmail[.]com
#
#Licensed under the Apache License, Version 2.0 (the "License");
#you may not use this file except in compliance with the License.
#You may obtain a copy of the License at
#
#    http://www.apache.org/licenses/LICENSE-2.0
#
#Unless required by applicable law or agreed to in writing, software
#distributed under the License is distributed on an "AS IS" BASIS,
#WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#See the License for the specific language governing permissions and
#limitations under the License.

#ioc2rpz.gui container

FROM alpine:latest
MAINTAINER Vadim Pavlov<pvm.del@gmail.com>
WORKDIR /opt/ioc2rpz.gui

RUN mkdir -p /run/apache2 /opt/ioc2rpz.gui/www /opt/ioc2rpz.gui/www/io2cfg  /opt/ioc2rpz.gui/cfg /opt/ioc2rpz.gui/scripts && apk update && apk upgrade && apk add bash openrc curl openssl apache2 libxml2-dev apache2-utils php7-apache2 php7-json php7-curl apache2-ssl sqlite php7-sqlite && \
    rm -rf /var/cache/apk/* /tmp/* /var/tmp/*; \
    sed -i -e "s%\(DocumentRoot\).*%\1 /opt/ioc2rpz.gui/www%" -e "s%^#\(.*mod_rewrite.so\).*%\1%"  /etc/apache2/httpd.conf; \
    echo -e "<Directory /opt/ioc2rpz.gui/www/>\nOptions FollowSymLinks\nAllowOverride Indexes\nRequire all granted\nRewriteEngine on\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule . /index.php [L]\n</Directory>\n"  >> /etc/apache2/httpd.conf
ADD www/* /opt/ioc2rpz.gui/www
ADD scripts/* /opt/ioc2rpz.gui/scripts

VOLUME ["/opt/ioc2rpz.gui/cfg", "/opt/ioc2rpz.gui/www/io2cfg"]


EXPOSE 80/tcp 443/tcp
CMD ["/bin/bash", "/opt/ioc2rpz.gui/scripts/ioc2rpz.gui.sh"]
