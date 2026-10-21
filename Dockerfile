FROM php:8.2-apache

# GD লাইব্রেরির জন্য প্রয়োজনীয় সিস্টেম ডিপেন্ডেন্সি ইনস্টল করা
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd

# Apache-এ mod_rewrite চালু করা (প্রয়োজন হলে)
RUN a2enmod rewrite

# প্রজেক্টের সব ফাইল কপি করা
COPY . /var/www/html/

# Apache রান করার জন্য পোর্ট এক্সপোজ করা
EXPOSE 80
