FROM alpine:3.4

MAINTAINER Spencer Kotowick "info@speko.io"

RUN apk --update add bash apache2 php5 php5-apache2 php5-curl php5-mysqli php5-openssl php5-json mysql-client postfix git curl vim

RUN cd /var/www/ \
  && git clone https://github.com/etsy/opsweekly.git opsweekly \
  && mkdir -p /run/apache2 \
  && chgrp www-data /run/apache2 \
  && chmod 775 /run/apache2 \
  && chown apache:apache logs \
  && chmod g+w /var/log/apache2 \
  && addgroup apache wheel \
  && mkdir -p /var/www/opsweekly/service/health \
  && mkfifo /var/spool/postfix/public/pickup

RUN apk del git \
  && rm -rf /var/cache/apk/* \
  && rm -rf /var/www/localhost

RUN postconf "smtputf8_enable = no" \
  && postfix start

ADD   apache/httpd.conf /etc/apache2/httpd.conf
ADD   apache/htpasswd /etc/htpasswd/.htpasswd
ADD   apache/health_check/index.html /var/www/opsweekly/service/health/index.html
COPY  opsweekly/ /var/www/opsweekly/

EXPOSE 80

ENTRYPOINT ["httpd","-D","FOREGROUND"]
