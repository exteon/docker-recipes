#TEMPLATE[ php7-rhel8 ]
#TEMPLATE[ apache-rhel8 ]

RUN dnf install -y \
    php-fpm

RUN mkdir /run/php-fpm
RUN crudini --set /etc/php-fpm.conf global error_log /proc/self/fd/2 && \
    crudini --set /etc/php-fpm.d/www.conf www catch_workers_output yes && \
    crudini --set /etc/php-fpm.d/www.conf www php_admin_value[error_log] /proc/self/fd/2
COPY $TEMPLATE_DIR/supervisord/php-fpm.conf /etc/supervisord.d/php-fpm.conf
