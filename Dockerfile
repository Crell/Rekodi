FROM php:8.0-cli
WORKDIR /usr/src/myapp
# CMD [ "vendor/bin/phpunit", "--filter=CompiledEventDispatcherAttributeTest" ]
CMD [ "vendor/bin/phpunit" ]
# CMD [ "php", "test.php" ]
