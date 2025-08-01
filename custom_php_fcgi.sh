#!/bin/sh
# Custom FastCGI script with extended timeout settings
export PHP_FCGI_CHILDREN=4
export PHP_FCGI_MAX_REQUESTS=200
export PHP_FCGI_IDLE_TIMEOUT=1200
export PHP_FCGI_PROCESS_TIMEOUT=1200

# Set PHP timeout environment variables
export PHP_MAX_EXECUTION_TIME=1200
export PHP_MEMORY_LIMIT=1024M

exec /Applications/MAMP/bin/php/php8.3.14/bin/php-cgi -c "/Applications/MAMP/bin/php/php8.3.14/conf/php.ini" 