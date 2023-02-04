export DISABLE_AUTO_TITLE="true"
echo -n -e "\033]0;White Horse Communication Shell\007"

export MYSQL_HOME="/home/lozza/.config/Local/run/6xWxZU_Fm/conf/mysql"
export PHPRC="/home/lozza/.config/Local/run/6xWxZU_Fm/conf/php"
export WP_CLI_CONFIG_PATH="/opt/Local/resources/extraResources/bin/wp-cli/config.yaml"
export WP_CLI_DISABLE_AUTO_CHECK_UPDATE=1

# Add PHP, MySQL, and WP-CLI to $PATH
echo "Setting Local environment variables..."

export PATH="/home/lozza/.config/Local/lightning-services/mysql-5.7.28+4/bin/linux/bin:$PATH"
export PATH="/opt/Local/resources/extraResources/lightning-services/php-8.1.9+8/bin/linux/bin:$PATH"
export PATH="/opt/Local/resources/extraResources/bin/wp-cli/posix:$PATH"
export PATH="/opt/Local/resources/extraResources/bin/composer/posix:$PATH"


export MAGICK_CODER_MODULE_PATH="/opt/Local/resources/extraResources/lightning-services/php-8.1.9+8/bin/linux/ImageMagick/modules-Q16/coders"

export LD_LIBRARY_PATH="/opt/Local/resources/extraResources/lightning-services/php-8.1.9+8/bin/linux/shared-libs"


echo "----"
echo "WP-CLI:   $(wp --version)"
echo "Composer: $(composer --version | cut -f3-4 -d" ")"
echo "PHP:      $(php -r "echo PHP_VERSION;")"
echo "MySQL:    $(mysql --version)"
echo "----"

cd "/home/lozza/Local Sites/white-horse-communication/app/public"




 wp db import ../sql/local.sql