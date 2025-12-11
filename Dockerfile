FROM php:8.2-apache

# Install required system packages for CURL
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    && docker-php-ext-install curl

# Copy your bot files
COPY . /var/www/html/

EXPOSE 80
