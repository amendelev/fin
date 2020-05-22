#!/bin/bash

# создаю папку xxx_byday в подпапке яндекс-диска
# создаю ссылку в fin/db

SID=$1
[ -z "$SID" ] && { echo "empty tarefa SID" && exit 1 ; }

ROOT=$PWD
Y_BYDAY="../yadi-yfinam/finar/${SID}_byday"

mkdir "../yadi-yfinam/finar/${SID}_byday" 
ln -s "${ROOT}/${Y_BYDAY}" "db/byday/${SID}"
