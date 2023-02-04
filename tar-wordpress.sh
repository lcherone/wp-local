#!/bin/bash

cp -r ./public ./tmp

rm ./tmp/wp-config.php

cd tmp

tar -zcvf ../wordpress.tgz .

cd ../

rm -Rf tmp
