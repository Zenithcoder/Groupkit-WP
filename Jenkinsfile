pipeline {
    agent none

    stages {
        stage("Run tests on branches other then production and staging without deploying them") {
            options {
                lock('unit_test_lock')
            }
            when {
                not {
                    anyOf {
                        branch 'production';
                        branch 'staging'
                    }
                }
            }
            agent {
                docker { 
                    image 'circleci/php:7.4-node-browsers' 
                    args '-u root'
                }
            }
            steps {
                // Prepare Environment
                sh 'wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | sudo apt-key add -'
                sh 'sudo apt update'
                sh 'sudo docker-php-ext-install zip'
                sh 'sudo docker-php-ext-install pdo_mysql'

                // Create Enviroment file
                sh 'mv .env.testing .env'

                // NPM run
                sh 'npm install'
                sh 'npm run prod'

                // Install Dependencies
                sh 'composer install -n --prefer-dist'

                // Generate App key
                sh 'php artisan key:generate'

                // Run tests
                sh './vendor/phpunit/phpunit/phpunit -d memory_limit=500M tests'
            }
        }
    }
}
