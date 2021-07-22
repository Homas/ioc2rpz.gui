#Copyright 2017-2020 Vadim Pavlov ioc2rpz[at]gmail[.]com
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
    apk add bash openrc curl coreutils openssl apache2 libxml2-dev apache2-utils php7 php7-apache2 php7-session php7-json php7-curl php7-pecl-ssh2 apache2-ssl sqlite php7-sqlite3 php7-ctype bind-tools knot-utils && \
    ln -sf /proc/self/fd/1 /var/log/apache2/access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/error.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_error.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/ssl_request.log && \
    rm -rf /var/cache/apk/* /tmp/* /var/tmp/*



#/etc/apache2/conf.d/mpm.conf
#<IfModule mpm_prefork_module>
#StartServers             1
#MinSpareServers          1
#MaxSpareServers          0
#MaxRequestWorkers      250
#MaxConnectionsPerChild   0
#</IfModule>

### Validate SSL

RUN sed -i -e "s/\(.*ServerTokens\).*/\1 Prod/"  /etc/apache2/httpd.conf && echo -e "TraceEnable Off\n"  >> /etc/apache2/httpd.conf && sed -i -e "s/^.*\(expose_php =\).*/\1 Off/" /etc/php7/php.ini && sed -i -e "s/^\(SSLProxyProtocol.*\)/#\1/" -e "s/^\(SSLProxyCipherSuite.*\)/#\1/" -e "s/^\(SSLProtocol\).*/SSLProtocol -all +TLSv1.2 +TLSv1.3/" -e "s/^\(SSLCipherSuite\).*/\1 TLSv1.3 TLS_AES_256_GCM_SHA384:TLS_AES_128_GCM_SHA256\n\1 SSL ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA256\nSSLOpenSSLConfCmd Curves X25519:secp521r1:secp384r1:prime256v1/" -e "/<VirtualHost _default_:443>/ a Header always set Strict-Transport-Security \"max-age=63072000; includeSubDomains\"" /etc/apache2/conf.d/ssl.conf

COPY www/* /opt/ioc2rpz.gui/www/
COPY www/js/* /opt/ioc2rpz.gui/www/js/
COPY www/css/* /opt/ioc2rpz.gui/www/css/
#COPY www/webfonts/* /opt/ioc2rpz.gui/www/webfonts/
#COPY www/img/* /opt/ioc2rpz.gui/www/img/
COPY scripts/* /opt/ioc2rpz.gui/scripts/

#Local CSS/JS
ADD https://unpkg.com/bootstrap@4.5.3/dist/css/bootstrap.min.css /opt/ioc2rpz.gui/www/css
ADD https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.css /opt/ioc2rpz.gui/www/css
ADD https://cdn.jsdelivr.net/npm/vue@latest/dist/vue.min.js /opt/ioc2rpz.gui/www/js
ADD https://unpkg.com/babel-polyfill@latest/dist/polyfill.min.js /opt/ioc2rpz.gui/www/js
ADD https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.js /opt/ioc2rpz.gui/www/js
ADD https://unpkg.com/axios/dist/axios.min.js /opt/ioc2rpz.gui/www/js
ADD https://use.fontawesome.com/releases/v5.12.1/fontawesome-free-5.12.1-web.zip /tmp

ENV Docker_CSS <link type=\"text/css\" rel=\"stylesheet\" href=\"/css/bootstrap.min.css\"/><link type=\"text/css\" rel=\"stylesheet\" href=\"/css/bootstrap-vue.min.css\"/><link rel=\"stylesheet\" href=\"/css/all.min.css\">
##" - bug in Komodo
ENV Docker_JS <script src=\"/js/vue.min.js\"></script><script src=\"/js/polyfill.min.js\"></script><script src=\"/js/bootstrap-vue.min.js\"></script><script src=\"/js/axios.min.js\"></script>
##" - bug in Komodo
#
RUN unzip /tmp/fontawesome-free-5.12.1-web.zip -d /tmp && cp /tmp/fontawesome-free-5.12.1-web/css/all.min.css /opt/ioc2rpz.gui/www/css/ && cp -r /tmp/fontawesome-free-5.12.1-web/webfonts /opt/ioc2rpz.gui/www/ && sed -i -e "s#^.*Docker_CSS.*#${Docker_CSS}#" -e "s#^.*Docker_JS.*#${Docker_JS}#" -e "s/^.*\(<!-- Docker_Comm_Start\).*/\1/" -e "s/^.*Docker_Comm_End.*/-->/" /opt/ioc2rpz.gui/www/index.php && sed -i -e "s#^.*Docker_CSS.*#${Docker_CSS}#" -e "s#^.*Docker_JS.*#${Docker_JS}#" -e "s/^.*\(<!-- Docker_Comm_Start\).*/\1/" -e "s/^.*Docker_Comm_End.*/-->/" /opt/ioc2rpz.gui/www/io2auth.php && chmod 644 /opt/ioc2rpz.gui/www/css/* && chmod 644 /opt/ioc2rpz.gui/www/js/* && chmod 644 /opt/ioc2rpz.gui/www/webfonts/* && rm -rf /tmp/*


VOLUME ["/opt/ioc2rpz.gui/export-cfg", "/opt/ioc2rpz.gui/www/io2cfg", "/etc/apache2/ssl"]


EXPOSE 80/tcp 443/tcp
CMD ["/bin/bash", "/opt/ioc2rpz.gui/scripts/run_ioc2rpz.gui.sh"]
