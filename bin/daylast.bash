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

# сводный отчет по шагу и стоимости лота за весь период
# для контроля
cd "${ROOT}/sum"
cat *-daylast.csv | perl -ne '@a=split ";"; if (!@prev || ( $a[6]!=$prev[6] || $a[7]!=$prev[7])) { print $a[0],";",$a[6],";",$a[7],"\n"}; @prev=@a;' > shag-lot.csv
echo sum/shag-lot.csv

