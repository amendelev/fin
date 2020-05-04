#!/bin/bash

# стартую раннеров

CNT=$1
SID=$2

PID=$$

for (( c=1; c<=CNT; c++ ))
do 
	php bin/console app:car passo passo=run sid=$SID >"var/run/$SID-$PID-$c" 2>&1 &
done

