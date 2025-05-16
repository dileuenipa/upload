# Sử dụng image PHP có Apache sẵn
FROM php:8.2-apache

# Copy toàn bộ source code (HTML + PHP) vào thư mục phục vụ web của Apache
COPY . /var/www/html/

# Set quyền cho thư mục web (nếu cần)
RUN chown -R www-data:www-data /var/www/html

# Mở port 80
EXPOSE 80

# Khởi động Apache (mặc định image này đã khởi động Apache)
CMD ["apache2-foreground"]
