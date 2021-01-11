#!/bin/bash

# This script prepares the server environment by installing additional software to the server. The installed software
# will be available in the Docker image. So, this script runs in the build time.

sudo apt-get update

# Installs and enables Xdebug
installXdebug() {
    echo "Installing Xdebug..."

    # In the future, if another version of xdebug is used and the PHPStorm hangs, click "Help > Show Log in Finder" to
    # see the logs. Because, it is quite hard to anticipate the problem is caused by an internal error. When the debug
    # tool of the IDE does not work as expected, the first thing to assume is that the Xdebug configuration might be
    # the cause. It is quite hard to validate that the error is not caused by the configuration of the IDE settings.
    yes | pecl install xdebug-2.8.1

    # Copy the Xdebug configuration file into the directory where PHP reads the configs to make PHP enable Xdebug
    cp /root/20-xdebug.ini /usr/local/etc/php/conf.d/20-xdebug.ini
}

# Installs Composer
installComposer() {
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer;
}

installXdebug
installComposer