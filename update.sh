#!/bin/sh
# update script for nosh2

set -e

# Constants and paths
LOGDIR=/var/log/nosh2
LOG=$LOGDIR/nosh2_installation_log
NOSHCRON=/etc/cron.d/nosh-cs
MYSQL_DATABASE=nosh
NOSH_DIR=/noshdocuments
OLDNOSH=$NOSH_DIR/nosh-cs
NEWNOSH=$NOSH_DIR/nosh2
ENV=$NEWNOSH/.env

log_only () {
	echo "$1"
	echo "`date`: $1" >> $LOG
}

unable_exit () {
	echo "$1"
	echo "`date`: $1" >> $LOG
	echo "EXITING.........."
	echo "`date`: EXITING.........." >> $LOG
	exit 1
}

get_settings () {
	echo `grep -i "^[[:space:]]*$1[[:space:]=]" $2 | cut -d \= -f 2 | cut -d \; -f 1 | sed "s/[ 	'\"]//gi"`
}

insert_settings () {
	sed -i 's%^[ 	]*'"$1"'[ 	=].*$%'"$1"' = '"$2"'%' "$3"
}

# Check if running as root user
if [[ $EUID -ne 0 ]]; then
	echo "This script must be run as root.  Aborting." 1>&2
	exit 1
fi

# Check if previous installation
if [ ! -d $OLDNOSH ]; then
	echo "No previous installation of NOSH found.  Aborting." 1>&2
	exit 1
fi

# Create log file if it doesn't exist
if [ ! -d $LOGDIR ]; then
	mkdir -p $LOGDIR
fi

# Check os and distro
if [[ "$OSTYPE" == "linux-gnu" ]]; then
	if [ -f /etc/debian_version ]; then
		# Ubuntu or Debian
		WEB_GROUP=www-data
		WEB_USER=www-data
		if [ -d /etc/apache2/conf-enabled ]; then
			WEB_CONF=/etc/apache2/conf-enabled
		else
			WEB_CONF=/etc/apache2/conf.d
		fi
		APACHE="/etc/init.d/apache2 restart"
		SSH="/etc/init.d/ssh stop"
		SSH1="/etc/init.d/ssh start"
	elif [ -f /etc/redhat-release ]; then
		# CentOS or RHEL
		WEB_GROUP=apache
		WEB_USER=apache
		WEB_CONF=/etc/httpd/conf.d
		APACHE="/etc/init.d/httpd restart"
		SSH="/etc/init.d/sshd stop"
		SSH1="/etc/init.d/sshd start"
	elif [ -f /etc/arch-release ]; then
		# ARCH
		WEB_GROUP=http
		WEB_USER=http
		WEB_CONF=/etc/httpd/conf/extra
		APACHE="systemctl restart httpd.service"
		SSH="systemctl stop sshd"
		SSH1="systemctl start sshd"
	elif [ -f /etc/gentoo-release ]; then
		# Gentoo
		WEB_GROUP=apache
		WEB_USER=apache
		WEB_CONF=/etc/apache2/modules.d
		APACHE=/etc/init.d/apache2
		SSH="/etc/init.d/sshd stop"
		SSH1="/etc/init.d/sshd start"
	elif [ -f /etc/fedora-release ]; then
		# Fedora
		WEB_GROUP=apache
		WEB_USER=apache
		WEB_CONF=/etc/httpd/conf.d
		APACHE="/etc/init.d/httpd restart"
		SSH="/etc/init.d/sshd stop"
		SSH1="/etc/init.d/sshd start"
	fi
elif [[ "$OSTYPE" == "darwin"* ]]; then
	# Mac
	WEB_GROUP=_www
	WEB_USER=_www
	WEB_CONF=/etc/httpd/conf.d
	APACHE="/usr/sbin/apachectl restart"
	SSH="launchctl unload com.openssh.sshd"
	SSH1="launchctl load com.openssh.sshd"
elif [[ "$OSTYPE" == "cygwin" ]]; then
	echo "This operating system is not supported by this install script at this time.  Aborting." 1>&2
	exit 1
elif [[ "$OSTYPE" == "win32" ]]; then
	echo "This operating system is not supported by this install script at this time.  Aborting." 1>&2
	exit 1
elif [[ "$OSTYPE" == "freebsd"* ]]; then
	WEB_GROUP=www
	WEB_USER=www
	WEB_CONF=/etc/httpd/conf.d
	if [ -e /usr/local/etc/rc.d/apache22.sh ]; then
		APACHE="/usr/local/etc/rc.d/apache22.sh restart"
	else
		APACHE="/usr/local/etc/rc.d/apache24.sh restart"
	fi
	SSH="/etc/rc.d/sshd stop"
	SSH1="/etc/rc.d/sshd start"
else
	echo "This operating system is not supported by this install script at this time.  Aborting." 1>&2
	exit 1
fi

# Check apache version
APACHE_VER=$(apache2 -v | awk -F"[..]" 'NR<2{print $2}')

# Update
cd $NOSH_DIR
composer create-project nosh2/nosh2 --prefer-dist --stability dev
cd $NEWNOSH

chown -R $WEB_GROUP.$WEB_USER $NEWNOSH
chmod -R 755 $NEWNOSH
chmod -R 777 $NEWNOSH/storage
chmod -R 777 $NEWNOSH/public
chmod 777 $NEWNOSH/noshfax
chmod 777 $NEWNOSH/noshreminder
chmod 777 $NEWNOSH/noshbackup
cp $OLDNOSH/.google $NEWNOSH/.google
log_only "Updated NOSH ChartingSystem core files."
a2enmod ssl
if [ -e "$WEB_CONF"/nosh.conf ]; then
	sed -i "s_Alias /nosh /noshdocuments/nosh-cs/public_Alias /nosh-old /noshdocuments/nosh-cs/public_" "$WEB_CONF"/nosh.conf
fi
if [ -e "$WEB_CONF"/nosh2.conf ]; then
	rm "$WEB_CONF"/nosh2.conf
fi
touch "$WEB_CONF"/nosh2.conf
APACHE_CONF="Alias /nosh $NEWNOSH/public
<Directory $NEWNOSH/public>
	Options Indexes FollowSymLinks MultiViews
	AllowOverride None"
if [ "$APACHE_VER" = "4" ]; then
	APACHE_CONF="$APACHE_CONF
	Require all granted"
else
	APACHE_CONF="$APACHE_CONF
	Order allow,deny
	allow from all"
fi
APACHE_CONF="$APACHE_CONF
	RewriteEngine On
	# Redirect Trailing Slashes...
	RewriteRule ^(.*)/$ /\$1 [L,R=301]
	RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	# Handle Front Controller...
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^ index.php [L]
	# Force SSL
	RewriteCond %{HTTPS} !=on
	RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
	<IfModule mod_php5.c>
		php_value upload_max_filesize 512M
		php_value post_max_size 512M
		php_flag magic_quotes_gpc off
		php_flag register_long_arrays off
	</IfModule>
</Directory>"
echo "$APACHE_CONF" >> "$WEB_CONF"/nosh2.conf
log_only "NOSH ChartingSystem Apache configuration file set."
log_only "Restarting Apache service."
$APACHE >> $LOG 2>&1
# Installation completed
log_only "You can now use NOSH ChartingSystem by browsing to:"
log_only "https://localhost/nosh"
log_only "The old version of NOSH ChartingSystem can still be used by browsing to:"
log_only "https://localhost/nosh-old"
exit 0
