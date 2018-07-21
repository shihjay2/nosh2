#!/bin/sh
# install script for nosh-2 on a droplet - Ubuntu 16.04 server

set -e

# Constants and paths
LOGDIR=/var/log/nosh2
LOG=$LOGDIR/nosh2_installation_log
NOSHCRON=/etc/cron.d/nosh-cs
MYSQL_DATABASE=nosh
NOSH_DIR=/noshdocuments
WEB_GROUP=www-data
WEB_USER=www-data
WEB_CONF=/etc/apache2/conf-enabled
NEWNOSH=$NOSH_DIR/nosh2
ENV=$NEWNOSH/.env
APACHE="/etc/init.d/apache2 restart"
MYSQL_USERNAME=hieofone

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

read -e -p "Enter your registered URL: " -i "" URL

# Install dependencies
apt-get update
apt-get -y install software-properties-common build-essential binutils-doc git subversion bc apache2 php php-cli php-common php-curl php-gd php-imagick php-imap php-mbstring php-mysql php-pear php-soap php-ssh2 php-xml php-zip libapache2-mod-php libdbi-perl libdbd-mysql-perl libssh2-1-dev imagemagick openssh-server pwgen jq
export DEBIAN_FRONTEND=noninteractive
# Randomly generated password for MariaDB
MYSQL_PASSWORD=`pwgen -s 40 1`
log_only "Your MariaDB password is $MYSQL_PASSWORD"
debconf-set-selections <<< "mariadb-server-10.1 mysql-server/data-dir select ''"
debconf-set-selections <<< "mariadb-server-10.1 mysql-server/root_password password $MYSQL_PASSWORD"
debconf-set-selections <<< "mariadb-server-10.1 mysql-server/root_password_again password $MYSQL_PASSWORD"
apt-get install -y mariadb-server mariadb-client
# Set default collation and character set
echo "[mysqld]
character_set_server = 'utf8'
collation_server = 'utf8_general_ci'" >> /etc/mysql/my.cnf
# Configure Maria Remote Access
sed -i '/^bind-address/s/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/my.cnf
mysql --user="root" --password="$MYSQL_PASSWORD" -e "GRANT ALL ON *.* TO root@'0.0.0.0' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION;"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "CREATE USER '$MYSQL_USERNAME'@'0.0.0.0' IDENTIFIED BY '$MYSQL_PASSWORD';"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "GRANT ALL ON *.* TO '$MYSQL_USERNAME'@'0.0.0.0' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION;"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "GRANT ALL ON *.* TO '$MYSQL_USERNAME'@'%' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION;"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "FLUSH PRIVILEGES;"
systemctl restart mysql

# Check prerequisites
type apache2 >/dev/null 2>&1 || { echo >&2 "Apache Web Server is required, but it's not installed.  Aborting."; exit 1; }
type mysql >/dev/null 2>&1 || { echo >&2 "MySQL is required, but it's not installed.  Aborting."; exit 1; }
type php >/dev/null 2>&1 || { echo >&2 "PHP is required, but it's not installed.  Aborting."; exit 1; }
type perl >/dev/null 2>&1 || { echo >&2 "Perl is required, but it's not installed.  Aborting."; exit 1; }
type curl >/dev/null 2>&1 || { echo >&2 "cURL is required, but it's not installed.  Aborting."; exit 1; }
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
echo "30 0    * * 1   root    /usr/local/bin/certbot-auto renew >>  /var/log/le-renew.log" >> $NOSHCRON
chown root.root $NOSHCRON
chmod 644 $NOSHCRON
log_only "Created NOSH ChartingSystem cron scripts."

phpenmod imap
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
log_only "This installation will create pNOSH (patient NOSH)."

# Build
cd $NOSH_DIR
composer create-project nosh2/nosh2 --prefer-dist --stability dev
cd $NEWNOSH
# pNOSH designation
if ! [ -f "$NEWNOSH"/.patientcentric ]; then
	touch "$NEWNOSH"/.patientcentric
fi
# Edit .env file
sed -i '/^DB_DATABASE=/s/=.*/='"$MYSQL_DATABASE"'/' $ENV
sed -i '/^DB_USERNAME=/s/=.*/='"$MYSQL_USERNAME"'/' $ENV
sed -i '/^DB_PASSWORD=/s/=.*/='"$MYSQL_PASSWORD"'/' $ENV
echo "TRUSTED_PROXIES=
URI=localhost

TWITTER_KEY=yourkeyfortheservice
TWITTER_SECRET=yoursecretfortheservice
TWITTER_REDIRECT_URI=https://example.com/login

GOOGLE_KEY=yourkeyfortheservice
GOOGLE_SECRET=yoursecretfortheservice
GOOGLE_REDIRECT_URI=https://example.com/login
" >> $ENV
SHA1=$(curl -s 'https://api.github.com/repos/shihjay2/nosh2/commits' | jq -r '.[0] .sha')
touch $NEWNOSH/.version
echo $SHA1 >> $NEWNOSH/.version
chown -R $WEB_GROUP.$WEB_USER $NEWNOSH
chmod -R 755 $NEWNOSH
chmod -R 777 $NEWNOSH/storage
chmod -R 777 $NEWNOSH/public
chmod 777 $NEWNOSH/noshfax
chmod 777 $NEWNOSH/noshreminder
chmod 777 $NEWNOSH/noshbackup
log_only "Installed NOSH ChartingSystem core files."
echo "create database $MYSQL_DATABASE" | sudo mysql -u $MYSQL_USERNAME -p$MYSQL_PASSWORD
php artisan migrate:install
php artisan migrate
log_only "Installed NOSH ChartingSystem database schema."
a2enmod rewrite
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

# Install LetsEncrypt
cd /usr/local/bin
wget https://dl.eff.org/certbot-auto
chmod a+x /usr/local/bin/certbot-auto
./certbot-auto --apache -d $URL
log_only "Let's Encrypt SSL certificate is set."

# Installation completed
log_only "You can now complete your new installation of NOSH ChartingSystem by browsing to:"
log_only "https://$URL/nosh"
exit 0
