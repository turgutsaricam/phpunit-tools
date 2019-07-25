FROM php:7.2

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

COPY ./env/php/setup-environment.sh /bin/
RUN chmod +x /bin/setup-environment.sh
RUN cd /bin && ./setup-environment.sh

COPY ./env/php/20-xdebug.ini /usr/local/etc/php/conf.d/20-xdebug.ini

VOLUME /usr/src/phpunit-tools
WORKDIR /usr/src/phpunit-tools

CMD [ "tail", "-f", "/dev/null" ]