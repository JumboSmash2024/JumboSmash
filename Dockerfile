# syntax=docker/dockerfile:1
FROM php:8.2.19-apache

# Enable mysqli
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Add git, for use downloading composer dependencies
RUN apt-get -y update
RUN apt-get -y install git

# Add composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer