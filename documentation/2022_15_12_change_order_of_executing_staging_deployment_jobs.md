# Change order of executing staging deployment jobs

## Problem
In the staging deploy script, we are reloading the job worker after executing all tests from the project. That means that we need to wait to do PRT after executing all tests which last for 15-20 minutes. 

## Solution
Put reload job worker before executing tests


Current order in deployment script:
```
6. ./vendor/bin/phpunit --coverage-clover coverage.xml tests
bash <(curl -s https://codecov.io/bash) -t code
```

```
7. sudo supervisorctl restart worker:*
```

After this PR we will just switch it like below:
```
6. sudo supervisorctl restart worker:*
```
```
7. ./vendor/bin/phpunit --coverage-clover coverage.xml tests
bash <(curl -s https://codecov.io/bash) -t code
```
