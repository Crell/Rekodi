FROM php:8.1.0alpha2-cli
WORKDIR /usr/src/myapp
CMD [ "vendor/bin/phpunit" ]
#CMD [ "ls", "-alF" ]
#CMD ["pwd"]
