#!/bin/sh
# install script for nosh-2

set -e

# Constants and paths
LOGDIR=/var/log/nosh2
LOG=$LOGDIR/nosh2_installation_log
NOSHCRON=/etc/cron.d/nosh-cs
MYSQL_DATABASE=nosh
NOSH_DIR=/noshdocuments
WEB_GROUP=www-data
WEB_GROUP=www-data
WEB_CONF=/etc/apache2/conf-enabled
FTPIMPORT=/srv/ftp/shared/import
FTPEXPORT=/srv/ftp/shared/export
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

# Create log file if it doesn't exist
if [ ! -d $LOGDIR ]; then
	mkdir -p $LOGDIR
fi

read -e -p "Enter your MySQL username: " -i "" MYSQL_USERNAME
read -e -p "Enter your MySQL password: " -i "" MYSQL_PASSWORD

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

# Check prerequisites
type apache2 >/dev/null 2>&1 || { echo >&2 "Apache Web Server is required, but it's not installed.  Aborting."; exit 1; }
type mysql >/dev/null 2>&1 || { echo >&2 "MySQL is required, but it's not installed.  Aborting."; exit 1; }
type php >/dev/null 2>&1 || { echo >&2 "PHP is required, but it's not installed.  Aborting."; exit 1; }
type perl >/dev/null 2>&1 || { echo >&2 "Perl is required, but it's not installed.  Aborting."; exit 1; }
type curl >/dev/null 2>&1 || { echo >&2 "cURL is required, but it's not installed.  Aborting."; exit 1; }
type pdftk >/dev/null 2>&1 || { echo >&2 "PDFTK is required, but it's not installed.  Aborting."; exit 1; }
type convert >/dev/null 2>&1 || { echo >&2 "ImageMagick is required, but it's not installed.  Aborting."; exit 1; }
type sshd >/dev/null 2>&1 || { echo >&2 "SSH Server is required, but it's not installed.  Aborting."; exit 1; }
log_only "All prerequisites for installation are met."

# Check apache version
APACHE_VER=$(apache2 -v | awk -F"[..]" 'NR<2{print $2}')

# Create cron scripts
if [ -f $NOSHCRON ]; then
	rm -rf $NOSHCRON
fi
touch $NOSHCRON
echo "*/10 *  * * *   root    $NEWNOSH/noshfax" >> $NOSHCRON
echo "*/1 *   * * *   root    $NEWNOSH/noshreminder" >> $NOSHCRON
echo "0 0     * * *   root    $NEWNOSH/noshbackup" >> $NOSHCRON
chown root.root $NOSHCRON
chmod 644 $NOSHCRON
log_only "Created NOSH ChartingSystem cron scripts."

# Set up SFTP
groupadd ftpshared
log_only "Group ftpshared does not exist.  Making group."
mkdir -p $FTPIMPORT
mkdir -p $FTPEXPORT
chown -R root:ftpshared /srv/ftp/shared
chmod 755 /srv/ftp/shared
chmod -R 775 $FTPIMPORT
chmod -R 775 $FTPEXPORT
chmod g+s $FTPIMPORT
chmod g+s $FTPEXPORT
log_only "The NOSH ChartingSystem SFTP directories have been created."
/usr/bin/gpasswd -a www-data ftpshared
cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak
log_only "Backup of SSH config file created."
sed -i '/Subsystem/s/^/#/' /etc/ssh/sshd_config
echo '
Subsystem sftp internal-sftp' >> /etc/ssh/sshd_config
echo 'Match Group ftpshared' >> /etc/ssh/sshd_config
echo 'ChrootDirectory /srv/ftp/shared' >> /etc/ssh/sshd_config
echo 'X11Forwarding no' >> /etc/ssh/sshd_config
echo 'AllowTCPForwarding no' >> /etc/ssh/sshd_config
echo 'ForceCommand internal-sftp' >> /etc/ssh/sshd_config
log_only "SSH config file updated."
log_only "Restarting SSH server service"
$SSH >> $LOG 2>&1
$SSH1 >> $LOG 2>&1

# Install
if [ -f /etc/debian_version ]; then
	if [ -d /etc/php5/mods-available ]; then
		if [ ! -f /etc/php5/mods-available/mcrypt.ini ]; then
			if ! [ -L /etc/php5/mods-available/mcrypt.ini ]; then
				ln -s /etc/php5/conf.d/mcrypt.ini /etc/php5/mods-available
				log_only "Enabled mycrpt module for PHP."
			fi
		fi
	fi
	if [ -f /usr/sbin/php5enmod ]; then
		php5enmod mcrypt
		php5enmod imap
		log_only "Enabled mycrpt module for PHP."
	fi
else
	log_only "Ensure you have enabled the mcrypt module for PHP.  Check you distribution help pages to do this."
fi
if [ ! -f /usr/local/bin/composer ]; then
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/local/bin/composer
fi
log_only "Installed composer.phar."
if [ -d $NOSH_DIR ]; then
	log_only "The NOSH ChartingSystem documents directory already exists."
else
	mkdir -p $NOSH_DIR
	log_only "The NOSH ChartingSystem documents directory has been created."
fi
chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"
chmod -R 755 $NOSH_DIR
if ! [ -d "$NOSH_DIR"/scans ]; then
	mkdir "$NOSH_DIR"/scans
	chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"/scans
	chmod -R 777 "$NOSH_DIR"/scans
fi
if ! [ -d "$NOSH_DIR"/received ]; then
	mkdir "$NOSH_DIR"/received
	chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"/received
fi
if ! [ -d "$NOSH_DIR"/sentfax ]; then
	mkdir "$NOSH_DIR"/sentfax
	chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"/sentfax
fi
log_only "The NOSH ChartingSystem scan and fax directories are secured."
log_only "The NOSH ChartingSystem documents directory is secured."
cd $NOSH_DIR
composer create-project nosh2/nosh2 --prefer-dist --stability dev
cd $NEWNOSH

# Edit .env file
sed -i '/^DB_DATABASE=/s/=.*/='"$MYSQL_DATABASE"'/' $ENV
sed -i '/^DB_USERNAME=/s/=.*/='"$MYSQL_USERNAME"'/' $ENV
sed -i '/^DB_PASSWORD=/s/=.*/='"$MYSQL_PASSWORD"'/' $ENV
sed -i '/^APP_DEBUG=/s/=.*/='"false"'/' $ENV
chown -R $WEB_GROUP.$WEB_USER $NEWNOSH
chmod -R 755 $NEWNOSH
chmod -R 777 $NEWNOSH/storage
chmod -R 777 $NEWNOSH/public
chmod 777 $NEWNOSH/noshfax
chmod 777 $NEWNOSH/noshreminder
chmod 777 $NEWNOSH/noshbackup
log_only "Installed NOSH ChartingSystem core files."
echo "create database $MYSQL_DATABASE" | mysql -u $MYSQL_USERNAME -p$MYSQL_PASSWORD
php artisan migrate:install
php artisan migrate
log_only "Installed NOSH ChartingSystem database schema."
a2enmod ssl
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
	RewriteCond %{HTTP_HOST} !=localhost
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
log_only "You can now complete your new installation of NOSH ChartingSystem by browsing to:"
log_only "https://localhost/nosh"
exit 0
