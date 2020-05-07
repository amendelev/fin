#!/bin/bash

#
# создаю daylast для указанного проекта
# временный скрипт, должно происходить по базе
# запускать из корневой директории проекта ( где .git)
#

SID=$1
[ -z "$SID" ] && { echo "empty tarefa SID" && exit 1 ; }
cd "db/byday/$SID" || { echo "no project dir" && exit 1 ; }

ROOT=$PWD

[ -d sum ] || mkdir sum

for  dir in `ls -d 2*`
do 
	cd "${ROOT}/${dir}"
	DAYLAST="../sum/${dir}-daylast.csv"
	ls *.csv | xargs tail -n1 | grep "^20" > "${DAYLAST}"
	echo "${DAYLAST}"
done

