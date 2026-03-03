#!/bin/sh
# Genera un certificado SSL autofirmado para desarrollo local
mkdir -p /etc/nginx/ssl

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/nginx/ssl/nginx.key \
  -out /etc/nginx/ssl/nginx.crt \
  -subj "/C=ES/ST=Spain/L=Madrid/O=ProyectoFinanzas/OU=Dev/CN=localhost"

echo "Certificado SSL generado correctamente."
