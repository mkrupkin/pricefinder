# docker/nginx/Dockerfile
FROM nginx:1.24-alpine

# Copy configuration files
COPY nginx.conf /etc/nginx/nginx.conf
COPY sites-available/default.conf /etc/nginx/sites-available/default
COPY sites-available/default.conf /etc/nginx/conf.d/default.conf

# Create necessary directories
RUN mkdir -p /etc/nginx/sites-enabled
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/

# Set proper permissions
RUN chown -R nginx:nginx /etc/nginx