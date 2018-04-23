#!/bin/sh
# ssl installation script

set -e

WEB_CONF=/etc/apache2/conf-enabled
UBUNTU_VER=$(lsb_release -rs)
APACHE_VER=$(apache2 -v | awk -F"[..]" 'NR<2{print $2}')

# Check if running as root user
if [[ $EUID -ne 0 ]]; then
	echo "This script must be run as root.  Aborting." 1>&2
	exit 1
fi

read -e -p "Enter your domain name (example.com): " -i "" DOMAIN

if [[ ! -z $DOMAIN ]]; then
	if [ ! -f /usr/local/bin/certbot-auto ]; then
		cd /usr/local/bin
		wget https://dl.eff.org/certbot-auto
		chmod a+x /usr/local/bin/certbot-auto
		./certbot-auto --apache -d $DOMAIN
	fi
	echo "SSL Certificate for $DOMAIN set"
	/etc/init.d/apache2 restart
	echo "Restarting Apache service."
fi
exit 0
