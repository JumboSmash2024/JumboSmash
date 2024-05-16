# syntax=docker/dockerfile:1
FROM php:8.2.19-apache

# Enable mysqli
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Add git
RUN apt-get -y update
RUN apt-get -y install git

# Add zip, for use downloading composer dependencies
RUN apt-get install -y libzip-dev zip
RUN docker-php-ext-install zip && docker-php-ext-enable zip

# Add composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer