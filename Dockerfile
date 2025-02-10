FROM php:7.4-fpm
LABEL Le Duong <leduong@me.com>
ENV DEBIAN_FRONTEND=noninteractive

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apt-get update -q \
    && apt-get install -qq -y curl nginx libcurl3-dev

# Install PHP curl extensions.
RUN docker-php-ext-install -j$(nproc) curl 
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Copy file cấu hình Nginx
COPY ./nginx.conf /etc/nginx/sites-available/default

# Copy source code PHP Proxy
COPY ./php/proxy.php /var/www/html/proxy.php
COPY ./php/proxy.sh /proxy.sh

# Phân quyền thực thi script
RUN chmod +x /proxy.sh
RUN touch /var/log/php_proxy.log && chmod 777 /var/log/php_proxy.log

# Mở cổng 3000
EXPOSE 3000

# Chạy proxy
CMD ["/proxy.sh"]