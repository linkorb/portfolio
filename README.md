Portfolio
=========

This repository contains a PHP library to handle Project Portfolios.

## Data model

The `Portfolio` is the root object. It contains a list of `Project` objects, where contain a list of `Activity` objects (i.e. Tasks, Todos, Cards, etc).

For full details on the datamodel, it's classes and properties, check out the API documentation

## API documentation

This repository uses phpdocumentor 3 to generate API documentation based on classes. To avoid dependency complexity, it is not required in composer.json. Instead, download the latest (v3) .phar from https://github.com/phpDocumentor/phpDocumentor/releases

### Generating api docs

    phpDocumentor.phar run # this loads it's configuration from phpdoc.xml
    php -S 0.0.0.0:8888 -t build/doc