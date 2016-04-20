#! /usr/bin/env bash

MAIN_FOLDER=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# Update repository
cd $MAIN_FOLDER/..
git pull

DAY=$(date +"%Y%m%d")
# Remove previous backup, create new one and commit it
rm -f data/live.zip
zip -j data/live.zip data/live.json
git add data/live.zip
git commit -m "Update data ($DAY)"
git push
