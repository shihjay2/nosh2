#!/bin/bash
# Run scheduler
while [ true ]
do
  php /var/www/nosh/artisan schedule:run
  sleep 60
done
