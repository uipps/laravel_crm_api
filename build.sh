#!/bin/sh
date ;
php artisan clear-compiled
php artisan cache:clear
php artisan config:clear
php artisan event:clear
php artisan route:clear
php artisan view:clear

# git -C /www/html/test/crm-api status  && \
git -C /www/html/test/crm-api checkout -- .  && \
git -C /www/html/test/crm-api clean -fd  && \
git -C /www/html/test/crm-api pull
