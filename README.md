### GroupKit Web Portal
[![Build Status](https://jenkins.guaranteed.build/buildStatus/icon?job=Groupkit+Staging)](https://jenkins.guaranteed.build/job/Groupkit%20Staging/)
[![codecov](https://codecov.io/gh/groupkitapp/Web-Portal/branch/staging/graph/badge.svg?token=SDFD2J8COU)](https://codecov.io/gh/groupkitapp/Web-Portal)

## installation Guide

*Step_1 - install the composer and npm in your system.

*Step_2 - run the composer.json file in command prompt and install this file.

	** composer install

*Step_3 - run the package.json file in command prompt and install this file.

	** npm install

*Step_4 - Change the respective config in .env file.

*Step_5 - run the below command to run migration and install passport

	** php artisan migrate

	** php artisan passport:install

*Step_6 - run the below command to two different prompt

	** npm run watch

	** php artisan serve

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
