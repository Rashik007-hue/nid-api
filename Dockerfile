# PHP 8.2 Apache বেস ইমেজ
FROM php:8.2-apache

# প্যাকেজ ইনস্টলেশনের সময় কোনো প্রম্পট যাতে না আসে
ENV DEBIAN_FRONTEND=noninteractive

# সিস্টেম ডিপেন্ডেন্সি এবং GD লাইব্রেরি ইনস্টল করা
# একই RUN কমান্ডে update ও install করা হয়েছে যাতে ক্যাশে সমস্যা না হয়
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apache-এ mod_rewrite চালু করা (প্রয়োজন হলে)
RUN a2enmod rewrite

# প্রজেক্টের সব ফাইল কন্টেইনারে কপি করা
COPY . /var/www/html/

# 🔴 Persistent Disk-এর জন্য 'data' ফোল্ডার তৈরি এবং পারমিশন সেট করা
RUN mkdir -p /var/www/html/api/data && chown -R www-data:www-data /var/www/html/api/data

# পোর্ট এক্সপোজ করা
EXPOSE 80
