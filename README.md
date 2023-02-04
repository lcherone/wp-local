# Local Setup

<!--

This repo contains the files which are in your Local path, for example if it is `/home/user/Local Sites/site-name/` the files inside `/home/user/Local Sites/site-name/app` is this repo.

You can safely delete the files inside that directory then git clone this repo into it, the idea is that this repo replaces them folders and files.

Then because of the nature of how WordPress works the Local URL is placed into the database rows, you must run wp-cli once you have cloned the repo so it fixes the urls in the database.

It's probably best to call your Local `site-name`, then it may reduce some issues.

Use a Custom Local setup, which is PHP 7.4.\*, Apache and MySQL 5.7.\* so there is no incompatibility between db imports and exports, plus it more closely matches the production server setup.

Then...

## Setup

**Note:** Local uses a unix socket for the connection to the database as its container based, the localhost which is shown in the wp-config.php is not localhost on the host but rather localhost inside the container that Local creates, so some addtional somewhat complicated steps need to be done to install and import the database.

Addditionally the port used for the web page is not exposed anywhere, it could be http://localhost:10039 or http://localhost:10058 or some other random port number.

### Automatic Setup

To make it as easy as possible and with less steps, run the `bash local-setup.sh` script, then open the site in your browser and go to: `/local-setup.php`, i.e if the site is running here: `http://localhost:10039/` then go to `http://localhost:10039/local-setup.php`, which will guide you though the rest of the setup, from importing the database to fixing the urls.

### Manual Setup

If your lucky and "Open Site Shell" works in Local, then you can use that to replace the urls using `wp search-replace "from url" "to url"`, more info about it can be found here: https://developer.wordpress.org/cli/commands/search-replace/

If "Open Site Shell" does not work then you can find the ssh-entry file and execute it to get a shell with the correct paths setup, on linux its `/home/<username>/.config/Local/ssh-entry`, then inside the folder there is a file like `theSiteId.sh` which you can run and it will set the correct paths, else wp-cli simply wont work due to wrong mysql socket used, so for example if the mysql socket is:

`/home/user/.config/Local/run/tXzlTdzhL/mysql/mysqld.sock`, which is shown in the Local apps Database section, then the file will be found and run like:

`bash /home/user/.config/Local/ssh-entry/tXzlTdzhL.sh`

Which when run your get:

```
user@system:~$ bash /home/user/.config/Local/ssh-entry/tXzlTdzhL.sh
Setting Local environment variables...
----
WP-CLI:   WP-CLI 2.5.0-alpha
Composer: 1.10.8 2020-06-24
PHP:      7.4.1
MySQL:    mysql  Ver 14.14 Distrib 5.7.28, for linux-glibc2.12 (x86_64) using  EditLine wrapper
----
Launching shell: /bin/bash ...
user@system:~/Local Sites/site-name/app/public$ 
```

Which then you can run `wp` functions.

Other then the above, setup would be a bog standard, clone, import database, then run the search and replace.

### Clone and Replace/Link WordPress Files

You could simply replace the files directly into the Locak Site directory or is better which is fine, but you can also just delete the public and sql folders and add a symbolic link for both the public an sql folders, to the actual files, this might be handy if you want to put wordpress, members app code in a folder on your desktop for example.

For example, if I cloned the repo onto my desktop, it would be located here: `/home/user/Desktop/site-name`

So the link for public would be:

`ln -s /home/user/Desktop/site-name/public /home/user/Local\ Sites/site-name/app/public`

And likewise, the link for sql would be:

`ln -s /home/user/Desktop/site-name/sql /home/user/Local\ Sites/site-name/app/sql`

Then if all went well, 2 new folders/links would appear inside `/home/user/Local\ Sites/site-name/app`, upon clicking they would show the files in the folder on the desktop.

If this is unclear you might just want to replace the folders.

-->