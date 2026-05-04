FROM php:8.1-fpm

# Instalar extensiones PDO para MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Instalar msmtp para envío de emails (apunta a MailHog)
RUN apt-get update && apt-get install -y msmtp && rm -rf /var/lib/apt/lists/*

# Configurar msmtp para enviar a MailHog
RUN echo "account default\nhost mailhog\nport 1025\nfrom noreply@vaulta.local\nauth off\ntls off" > /etc/msmtprc

# Configuración de PHP
RUN echo "session.save_path = /tmp" >> /usr/local/etc/php/conf.d/session.ini
RUN echo "sendmail_path = /usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/mail.ini
