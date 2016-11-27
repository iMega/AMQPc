FROM alpine:3.4

RUN echo "@testing http://dl-4.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories && \
    echo "@community http://dl-4.alpinelinux.org/alpine/edge/community" >> /etc/apk/repositories && \
    echo "@main http://dl-4.alpinelinux.org/alpine/edge/main" >> /etc/apk/repositories && \
    apk add --update \
        php5 \
        php5-common \
        php5-dom \
        php5-json \
        libressl@main \
        php5-xdebug@community \
        rabbitmq-c@testing \
        php5-amqp@testing && \
    rm -rf /var/cache/apk/*

VOLUME /data

WORKDIR /data
