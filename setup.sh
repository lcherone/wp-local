#!/bin/bash

# copy local-setup.php to public
if [ ! -e public/local-setup.php ]
then
    cp setup/local-setup.php public/local-setup.php
fi

# copy local wp-config.php to public
if [ ! -e public/wp-config.php ]
then
    cp setup/wp-config.php public/wp-config.php
fi

#
sitename=$(php public/local-setup.php sitename)

echo "Files copied!"
echo "Next step:"
echo " - Make the sure the site is running then open up the site in your web browser via the Local app"
echo " - Then go to: http://$sitename.local/local-setup.php"
echo "   Note: It may not be the correct URL if you used a dash in the site name"
echo "         There is no way to know the correct site name from this step, use the correct site url from within the Local app."
echo "   Basically you just need to append /local-setup.php to the end of the URL, to access a PHP script which has been placed inside the site folder."
