# Install

## Install dependencies

```
composer install
```

## First run
Run script at first time:
```
php index.php
```
1) Then answer with "m" to question: _Do you want to enter the API id and the API hash manually or automatically?_, 
then follow instructions
2) Answer "u" to question about login as bot or user
3) Set forwarded from and to groups
4) Add command to cron, ex.: parse each minute is:
```
* * * * * /usr/bin/php -f path_to_project/index.php > /dev/null 2>&1
```
