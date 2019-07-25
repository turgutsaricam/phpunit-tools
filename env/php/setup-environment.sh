#!/bin/bash

# This script prepares the server environment by installing additional software to the server. The installed software
# will be available in the Docker image. So, this script runs in the build time.

sudo apt-get update

# Installs and enables Xdebug
installXdebug() {
    echo "Installing Xdebug..."

    # PHPStorm does not work with 2.7.0. The breakpoints hang, which means no debug output is shown in the IDE and
    # IDE just waits for an eternity if the debugging is not stopped. See the following comment:
    #   https://intellij-support.jetbrains.com/hc/en-us/community/posts/360001498520/comments/360000413060
    #
    # In the future, if another version of xdebug is used and the PHPStorm hangs, click "Help > Show Log in Finder" to
    # see the logs. Because, it is quite hard to anticipate the problem is caused by an internal error. When the debug
    # tool of the IDE does not work as expected, the first thing to assume is that the Xdebug configuration might be
    # the cause. It is quite hard to validate that the error is not caused by the configuration of the IDE settings.
    yes | pecl install xdebug-2.6.0

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