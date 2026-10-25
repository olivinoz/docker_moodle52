# 1. Base Image (Moodle 5.2 exige PHP 8.3)
FROM php:8.3-apache

# 2. Configuración del Entorno
ENV MOODLE_DATA_DIR /var/moodledata
# Apuntando a la rama correcta de Moodle 5.2
ENV MOODLE_VERSION MOODLE_502_STABLE
ENV MOODLE_ROOT /var/www/html

# 3. Instalación de Dependencias (Con POSTGRESQL y requerimientos de Moodle 5.2)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        libgd-dev \
        libsodium-dev \
        postgresql-client \
        git \
    && docker-php-ext-install \
        intl \
        pgsql \
        pdo_pgsql \
        soap \
        zip \
        gd \
        opcache \
        sodium \
        exif \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 3.5. Configuración PHP personalizada para Moodle
COPY ./moodle.ini /usr/local/etc/php/conf.d/moodle.ini

# 4. Descarga e Instalación del Código de Moodle
WORKDIR $MOODLE_ROOT
RUN git clone --depth 1 -b $MOODLE_VERSION https://github.com/moodle/moodle.git .

# 5. Configuración del Servidor Web (Ajuste para Moodle 5.x)
RUN a2enmod rewrite

# --- Ajustar el DocumentRoot a la carpeta /public ---
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. Definición del Directorio de Datos (moodledata) y PERMISOS
RUN mkdir -p $MOODLE_DATA_DIR \
    && chown -R www-data:www-data $MOODLE_ROOT $MOODLE_DATA_DIR \
    && chmod -R 0770 $MOODLE_DATA_DIR \
    && chmod -R 0755 $MOODLE_ROOT

# 7. Entrypoint Personalizado y Script de Setup API
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
COPY ./setup_api_rest.php /usr/local/bin/setup_api_rest.php
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/setup_api_rest.php

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]