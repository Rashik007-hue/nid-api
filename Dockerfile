FROM php:8.2-apache

# GD এবং mbstring লাইব্রেরি ইনস্টল করা (যা ইমেজ ও স্ট্রিং প্রসেসিংয়ের জন্য লাগে)
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd mbstring

# Apache-এ mod_rewrite চালু করা
RUN a2enmod rewrite

# সব ফাইল কন্টেইনারে কপি করা
COPY . /var/www/html/

# পারমিশন ঠিক করা (যাতে accounts.json রাইট করা যায়)
RUN chown -R www-data:www-data /var/www/html/api

EXPOSE 80
