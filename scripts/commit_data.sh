#! /usr/bin/env bash

MAIN_FOLDER=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# Update repository
cd $MAIN_FOLDER/..
git pull

DAY=$(date +"%Y%m%d")
# Remove previous file current.zip, create new one and commit it
rm -f archive/current.zip
zip -j archive/current.zip logs/live.json
git add archive/current.zip
git commit -m "Update data ($DAY)"
git push
