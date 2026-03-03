FROM php:8.1-fpm

# Instalar extensiones PDO para MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Configuración de PHP
RUN echo "session.save_path = /tmp" >> /usr/local/etc/php/conf.d/session.ini
