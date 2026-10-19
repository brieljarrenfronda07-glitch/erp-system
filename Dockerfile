FROM php:8.3-apache-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY deploy/apache.conf /etc/apache2/conf-available/petcare.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/petcare.ini
RUN a2enconf petcare
COPY index.php api.php health.php ./
COPY app.js printing.js style.css print.css ./
COPY permissions.js ./
COPY activity.js ./
COPY billing.js ./
COPY workflow.js ./
COPY doctor.js ./
COPY assets/ ./assets/
COPY deploy/start.sh /usr/local/bin/petcare-start
RUN sed -i 's/\r$//' /usr/local/bin/petcare-start \
    && chmod +x /usr/local/bin/petcare-start
ENV APP_ENV=production DATA_DIR=/var/data PORT=10000
EXPOSE 10000
CMD ["petcare-start"]
