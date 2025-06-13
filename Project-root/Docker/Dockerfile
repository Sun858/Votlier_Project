# Use an official PHP-Apache image
FROM php:8.2-apache

# Install necessary PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite (important for Laravel and frameworks)
RUN a2enmod rewrite

# Set the working directory
WORKDIR /var/www/html

#Create the directory that is needed.
RUN mkdir -p /var/www/html

# Copy project files into the container
COPY ../. /var/www/html

#change ownership of files.
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for Apache
EXPOSE 80