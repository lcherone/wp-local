#!/bin/bash

if [ ! -e wordpress.tgz ]
then
    echo "wordpress.tgz not found"
    exit 1
fi

cp wordpress.tgz ./public/wordpress.tgz

cd public

tar -zxvf wordpress.tgz

rm wordpress.tgz
