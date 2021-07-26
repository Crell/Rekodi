FROM php:8.0.8-cli
WORKDIR /usr/src/myapp
CMD [ "vendor/bin/wait-for-it.sh", "db:3306", "--", "vendor/bin/phpunit" ]
#CMD [ "vendor/bin/wait-for-it.sh", "db:3306", "--", "vendor/bin/phpunit", "--filter", "SchemaCreatorTest" ]
#CMD [ "ls", "-alF" ]
#CMD ["pwd"]

#RUN pecl install xdebug \
#    && docker-php-ext-install pdo_mysql

RUN docker-php-ext-install pdo_mysql
