#Copyright 2017-2019 Vadim Pavlov ioc2rpz[at]gmail[.]com
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
    /opt/ioc2rpz.gui/export-cfg /opt/ioc2rpz.gui/scripts && \
    apk add bash openrc curl coreutils openssl apache2 libxml2-dev apache2-utils php7 php7-apache2 php7-session php7-json php7-curl apache2-ssl sqlite php7-sqlite3 php7-ctype bind-tools knot-utils && \
    ln -sf /proc/self/fd/1 /var/log/apache2/access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/error.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_error.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_request.log && \
    rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

RUN sed -i -e "s/\(.*ServerTokens\).*/\1 Prod/"  /etc/apache2/httpd.conf
#Update index.php and io2comm_auth.php with local CSS/JS

#Local CSS/JS
ADD https://unpkg.com/bootstrap/dist/css/bootstrap.min.css /opt/ioc2rpz.comm/www/css
ADD https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.css /opt/ioc2rpz.comm/www/css
ADD https://cdn.jsdelivr.net/npm/vue@2.5.22/dist/vue.min.js /opt/ioc2rpz.comm/www/js
ADD https://unpkg.com/babel-polyfill@latest/dist/polyfill.min.js /opt/ioc2rpz.comm/www/js
ADD https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.js /opt/ioc2rpz.comm/www/js
ADD https://unpkg.com/axios/dist/axios.min.js /opt/ioc2rpz.comm/www/js
ADD https://use.fontawesome.com/releases/v5.9.0/fontawesome-free-5.9.0-web.zip /tmp
RUN unzip /tmp/fontawesome-free-5.9.0-web.zip -d /tmp && cp /tmp/fontawesome-free-5.9.0-web/css/all.css /opt/ioc2rpz/www/css && cp -r /tmp/fontawesome-free-5.9.0-web/webfonts /opt/ioc2rpz.gui/www/ && sed -i -e "s#^.*Docker_CSS.*#${Docker_CSS}#" -e "s#^.*Docker_JS.*#${Docker_JS}#" -e "s/^.*\(<!-- Docker_Comm_Start\).*/\1/" -e "s/^.*Docker_Comm_End.*/-->/" /opt/ioc2rpz.gui/www/index.php && sed -i -e "s#^.*Docker_CSS.*#${Docker_CSS}#" -e "s#^.*Docker_JS.*#${Docker_JS}#" -e "s/^.*\(<!-- Docker_Comm_Start\).*/\1/" -e "s/^.*Docker_Comm_End.*/-->/" /opt/ioc2rpz.gui/www/io2auth.php && chmod 644 /opt/ioc2rpz.gui/www/css/* && chmod 644 /opt/ioc2rpz.gui/www/js/* && chmod 644 /opt/ioc2rpz.gui/www/webfonts/* && rm -rf /tmp/*

COPY www/* /opt/ioc2rpz.gui/www/
COPY www/js/* /opt/ioc2rpz.gui/www/js/
COPY www/css/* /opt/ioc2rpz.gui/www/css/
COPY www/webfonts/* /opt/ioc2rpz.gui/www/webfonts/
#COPY www/img/* /opt/ioc2rpz.gui/www/img/
COPY scripts/* /opt/ioc2rpz.gui/scripts/

VOLUME ["/opt/ioc2rpz.gui/export-cfg", "/opt/ioc2rpz.gui/www/io2cfg", "/etc/apache2/ssl"]


EXPOSE 80/tcp 443/tcp
CMD ["/bin/bash", "/opt/ioc2rpz.gui/scripts/run_ioc2rpz.gui.sh"]
