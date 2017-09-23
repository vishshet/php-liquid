# docker build -t phpliquid .
# docker run phpliquid
FROM helder/php-5.3
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp
RUN php -v
CMD [ "php", "vendor/bin/phpunit" ]

