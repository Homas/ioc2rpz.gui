#Copyright 2017-2018 Vadim Pavlov ioc2rpz[at]gmail[.]com
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
MAINTAINER Vadim Pavlov<ioc2rpz@gmail.com>
WORKDIR /opt/ioc2rpz.gui

RUN mkdir -p /run/apache2 /etc/apache2/ssl /opt/ioc2rpz.gui/www /opt/ioc2rpz.gui/www/js /opt/ioc2rpz.gui/www/css /opt/ioc2rpz.gui/www/webfonts /opt/ioc2rpz.gui/img /opt/ioc2rpz.gui/www/io2cfg \
    /opt/ioc2rpz.gui/export-cfg /opt/ioc2rpz.gui/scripts && apk update && apk upgrade && \
    apk add bash openrc curl coreutils openssl apache2 libxml2-dev apache2-utils php7 php7-apache2 php7-session php7-json php7-curl apache2-ssl sqlite php7-sqlite3 php7-ctype bind-tools knot-resolver && \
    ln -sf /proc/self/fd/1 /var/log/apache2/access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/error.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_error.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_request.log && \
    rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

ADD www/* /opt/ioc2rpz.gui/www/
ADD www/js/* /opt/ioc2rpz.gui/www/js/
ADD www/css/* /opt/ioc2rpz.gui/www/css/
ADD www/webfonts/* /opt/ioc2rpz.gui/www/webfonts/
#ADD www/img/* /opt/ioc2rpz.gui/www/img/
ADD scripts/* /opt/ioc2rpz.gui/scripts/

VOLUME ["/opt/ioc2rpz.gui/export-cfg", "/opt/ioc2rpz.gui/www/io2cfg", "/etc/apache2/ssl"]


EXPOSE 80/tcp 443/tcp
CMD ["/bin/bash", "/opt/ioc2rpz.gui/scripts/run_ioc2rpz.gui.sh"]
