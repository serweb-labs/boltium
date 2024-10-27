#!/bin/sh

DEFAULT_CONF="/etc/nginx/conf.d/default.conf"
SSL_CONF="/etc/nginx/conf.d/ssl.conf"

if [ "$ENABLE_SSL" = "1" ]; then
  echo "SSL habilitado, creando ssl.conf con el dominio."
  
  envsubst '$DOMAIN' < /etc/nginx/conf.d/ssl.conf.template > "$SSL_CONF"
  
  [ -f "$DEFAULT_CONF" ] && rm "$DEFAULT_CONF"
else
  echo "SSL disabled, creating default.conf."
  envsubst '$DOMAIN' < /etc/nginx/conf.d/default.conf.template > "$DEFAULT_CONF"

  [ -f "$SSL_CONF" ] && rm "$SSL_CONF"
fi

nginx -g 'daemon off;'
