#Copyright 2017-2026 Vadim Pavlov ioc2rpz[at]gmail[.]com
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

# =============================================================================
# Stage 1: Build frontend assets with Node.js and Vite
# =============================================================================
FROM node:18-alpine AS builder

WORKDIR /build

# Copy package files first for better layer caching
COPY package.json package-lock.json ./

# Install dependencies
RUN npm ci

# Copy source files needed for build
COPY vite.config.js ./
COPY www/src ./www/src
COPY www/js ./www/js

# Build production assets
RUN npm run build

# =============================================================================
# Stage 2: Final runtime image
# =============================================================================
FROM alpine:latest
LABEL maintainer="Vadim Pavlov <ioc2rpz@gmail.com>"
WORKDIR /opt/ioc2rpz.gui

RUN mkdir -p /run/apache2 /etc/apache2/ssl /opt/ioc2rpz.gui/www /opt/ioc2rpz.gui/www/js /opt/ioc2rpz.gui/www/css /opt/ioc2rpz.gui/www/webfonts /opt/ioc2rpz.gui/www/dist /opt/ioc2rpz.gui/img /opt/ioc2rpz.gui/www/io2cfg \
    /opt/ioc2rpz.gui/export-cfg /opt/ioc2rpz.gui/scripts && \
    apk add bash openrc curl coreutils openssl apache2 libxml2-dev apache2-utils php83 php83-apache2 php83-session php83-curl php83-pecl-ssh2 apache2-ssl sqlite php83-sqlite3 php83-ctype bind-tools knot-utils && \
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

RUN sed -i -e "s/\(.*ServerTokens\).*/\1 Prod/"  /etc/apache2/httpd.conf && echo -e "TraceEnable Off\n"  >> /etc/apache2/httpd.conf && sed -i -e "s/^.*\(expose_php =\).*/\1 Off/" /etc/php83/php.ini && sed -i -e "s/^\(SSLProxyProtocol.*\)/#\1/" -e "s/^\(SSLProxyCipherSuite.*\)/#\1/" -e "s/^\(SSLProtocol\).*/SSLProtocol -all +TLSv1.2 +TLSv1.3/" -e "s/^\(SSLCipherSuite\).*/\1 TLSv1.3 TLS_AES_256_GCM_SHA384:TLS_AES_128_GCM_SHA256\n\1 SSL ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA256\nSSLOpenSSLConfCmd Curves X25519:secp521r1:secp384r1:prime256v1/" -e "/<VirtualHost _default_:443>/ a Header always set Strict-Transport-Security \"max-age=63072000; includeSubDomains\"" /etc/apache2/conf.d/ssl.conf

# Copy PHP files and application code
COPY www/*.php /opt/ioc2rpz.gui/www/
COPY www/js/*.js /opt/ioc2rpz.gui/www/js/
COPY www/css/* /opt/ioc2rpz.gui/www/css/
COPY scripts/* /opt/ioc2rpz.gui/scripts/

# Copy Vite-built assets from builder stage (includes manifest.json)
COPY --from=builder /build/www/dist /opt/ioc2rpz.gui/www/dist

# FontAwesome - download and extract for local serving
ADD https://use.fontawesome.com/releases/v5.12.1/fontawesome-free-5.12.1-web.zip /tmp

RUN unzip /tmp/fontawesome-free-5.12.1-web.zip -d /tmp && \
    cp /tmp/fontawesome-free-5.12.1-web/css/all.min.css /opt/ioc2rpz.gui/www/css/ && \
    cp -r /tmp/fontawesome-free-5.12.1-web/webfonts /opt/ioc2rpz.gui/www/ && \
    chmod 644 /opt/ioc2rpz.gui/www/css/* && \
    chmod 644 /opt/ioc2rpz.gui/www/js/* && \
    chmod 644 /opt/ioc2rpz.gui/www/webfonts/* && \
    chmod -R 644 /opt/ioc2rpz.gui/www/dist/* && \
    find /opt/ioc2rpz.gui/www/dist -type d -exec chmod 755 {} \; && \
    rm -rf /tmp/*


VOLUME ["/opt/ioc2rpz.gui/export-cfg", "/opt/ioc2rpz.gui/www/io2cfg", "/etc/apache2/ssl"]


EXPOSE 80/tcp 443/tcp
CMD ["/bin/bash", "/opt/ioc2rpz.gui/scripts/run_ioc2rpz.gui.sh"]
