ARG DEFAULT_TIMEZONE=Europe/Bucharest

RUN dnf install -y \
		php \
		php-pdo \
		php-dba \
		php-mbstring \
		php-gd \
		php-intl \
		php-opcache \
		php-json \
		php-posix

RUN crudini --set /etc/php.ini Date date.timezone $DEFAULT_TIMEZONE && \
    crudini --set /etc/php.d/10-opcache.ini "" opcache.revalidate_freq 0

################################################################################
# Set up composer                                                              #
################################################################################

RUN dnf install -y \
		php-json \
        php-zip \
        unzip
RUN mkdir /tmp/setup
RUN php -r "copy('https://getcomposer.org/installer', '/tmp/setup/composer-setup.php');" && \
	php /tmp/setup/composer-setup.php --install-dir=/usr/local/bin --filename=composer
