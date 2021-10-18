FROM centos:8

ARG DEFAULT_TIMEZONE=Europe/Bucharest

RUN dnf update -y

RUN dnf install -y \
		epel-release

RUN dnf install -y \
        rsyslog \
        sudo \
        crudini \
        glibc-all-langpacks \
        perl \
        supervisor

RUN ln -sf /usr/share/zoneinfo/$DEFAULT_TIMEZONE /etc/localtime

################################################################################
# Set up syslog                                                                #
################################################################################

# https://www.projectatomic.io/blog/2014/09/running-syslog-within-a-docker-container/
RUN perl -0777pi -e 's/module\s*\(\s*load="imuxsock"(#.*?\n|[^#])*?\)(\s*(#.*?)\n)*/module(load="imuxsock")\n/s' /etc/rsyslog.conf && \
    perl -0777pi -e 's/module\s*\(\s*load="imjournal"(#.*?\n|[^#])*?\)(\s*(#.*?)\n)*//s' /etc/rsyslog.conf

# Output syslog to stdout
COPY $TEMPLATE_DIR/rsyslogd/stdout.conf /etc/rsyslog.d/stdout.conf

################################################################################
# supervisor                                                                   #
################################################################################

COPY $TEMPLATE_DIR/supervisord/supervisord.conf /etc/supervisord.conf
COPY $TEMPLATE_DIR/supervisord/rsyslogd.conf /etc/supervisord.d/rsyslogd.conf

CMD /usr/bin/supervisord -nc /etc/supervisord.conf
