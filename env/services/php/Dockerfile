FROM php:7.2-apache-stretch

RUN apt-get update && apt-get install -y \
    sudo \
    less \
    vim

# See: https://stackoverflow.com/a/48700777/2883487
RUN apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-configure zip --with-libzip \
  && docker-php-ext-install zip

COPY ./env/services/php/20-xdebug.ini /root

COPY ./env/services/php/setup-environment.sh /bin/
RUN chmod +x /bin/setup-environment.sh
RUN cd /bin && ./setup-environment.sh

VOLUME /usr/src/phpunit-tools
WORKDIR /usr/src/phpunit-tools

CMD ["apache2-foreground"]